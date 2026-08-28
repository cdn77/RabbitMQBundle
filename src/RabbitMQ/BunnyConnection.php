<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\RabbitMQ;

use Bunny\ChannelInterface;
use Bunny\Client;
use Bunny\ClientInterface;
use Bunny\Configuration as BunnyConfiguration;
use Bunny\Defaults;
use Cdn77\RabbitMQBundle\Configuration;
use Cdn77\RabbitMQBundle\Exception\CannotCreateChannel;
use Cdn77\RabbitMQBundle\Exception\ConnectionFailed;
use Cdn77\RabbitMQBundle\Exception\OperationFailed;
use Closure;
use React\EventLoop\Loop;
use React\Promise\Deferred;
use Throwable;

use function microtime;
use function React\Async\async;
use function React\Async\await;
use function React\Promise\race;

final class BunnyConnection implements Connection
{
    /** @var BunnyConfiguration */
    private $configuration;

    /** @var float */
    private $operationTimeout;

    /** @var int */
    private $heartbeat;

    /** @var Client */
    private $client;

    /** @var ChannelInterface|null */
    private $channel;

    /** @var ChannelInterface|null */
    private $transactionalChannel;

    /** @var float|null */
    private $lastOperationAt;

    /** @var int */
    private $runningOperations = 0;

    public function __construct(Configuration\Connection $configuration)
    {
        $this->configuration = new BunnyConfiguration(
            host: $configuration->getHost(),
            port: $configuration->getPort(),
            vhost: $configuration->getVhost(),
            user: $configuration->getUser() ?? Defaults::USER,
            password: $configuration->getPassword() ?? Defaults::PASSWORD,
            timeout: $configuration->getConnectionTimeout(),
            heartbeat: (float) $configuration->getHeartbeat(),
        );
        $this->operationTimeout = $configuration->getOperationTimeout();
        $this->heartbeat = $configuration->getHeartbeat();
        $this->client = new Client($this->configuration);
    }

    public function getChannel(): ChannelInterface
    {
        $channel = $this->channel;
        if ($channel === null) {
            $channel = $this->createChannel();
            $channel->once('close', function (): void {
                $this->channel = null;
            });

            $this->channel = $channel;
        }

        return $channel;
    }

    public function getTransactionalChannel(): ChannelInterface
    {
        if ($this->transactionalChannel === null) {
            $channel = $this->createChannel();
            $channel->once('close', function (): void {
                $this->transactionalChannel = null;
            });

            try {
                $channel->txSelect();
            } catch (Throwable $exception) {
                throw new CannotCreateChannel('Cannot create transaction channel', 0, $exception);
            }

            // Cache it only once it is transactional, otherwise a retry would hand back a plain
            // channel from the fast path above and fail on txCommit() instead.
            $this->transactionalChannel = $channel;
        }

        return $this->transactionalChannel;
    }

    public function connect(): void
    {
        if ($this->client->canDisconnect()) {
            return;
        }

        // Bunny never rolls the state back when connect() fails, leaving a client that reports
        // itself as connected yet refuses to connect again - so every later attempt would await a
        // promise nothing can resolve. Such a client is unusable; start over with a fresh one.
        if ($this->client->isConnected()) {
            $this->discardClient();
        }

        try {
            $this->run(function (): void {
                $this->client->connect();
            });
        } catch (Throwable $exception) {
            throw ConnectionFailed::causedBy($exception);
        }
    }

    public function disconnect(): void
    {
        // The connection.close handshake first, for what it flushes rather than for the courtesy: a
        // publish() only reaches the socket once the loop turns again, and the local teardown below
        // closes the stream with those bytes still in React's write buffer, which drops them. The
        // broker then logs a connection that vanished without a connection.close and the message is
        // simply gone - the same for an acknowledge issued as the last thing a consumer does. The
        // handshake awaits a reply, so the loop turns and the buffer goes out ahead of it.
        //
        // Bounded by run(), because this is where a broker that has gone away must not be able to
        // hold the process: it runs on kernel.terminate and console.terminate. Skipped for a
        // connection idle past its heartbeat, which the broker has closed already - nothing
        // buffered on it can arrive any more, and run() would only replace it and then find nothing
        // to disconnect.
        if ($this->client->canDisconnect() && ! $this->isStale()) {
            try {
                $this->run(function (): void {
                    $this->client->disconnect(0, 'Connection closed');
                });
            } catch (Throwable) {
                // Nothing to add: the teardown below needs no broker, and run() has discarded the
                // client already.
            }
        }

        $this->discardClient();
    }

    /**
     * {@inheritDoc}
     *
     * Bounded by the configured operation timeout, and given up on as soon as the client reports an
     * error, so that a broker which stops answering - or a socket silently blackholed by a firewall
     * - cannot park the caller forever. Bunny awaits every protocol reply on a promise that only an
     * incoming frame settles: nothing rejects it when the other end goes away.
     *
     * Giving up leaves the operation's Fiber suspended inside the event loop for good, since there
     * is nothing left to resume it. The client holding it is discarded here so those references die
     * with the process instead of accumulating, but that Fiber is a real - if bounded - leak on the
     * failure path.
     *
     * @param Closure(): T $operation
     *
     * @return T
     *
     * @template T
     */
    public function run(Closure $operation): mixed
    {
        $this->replaceStaleClient();

        // Not only zero: a negative bound would be a timer already due, ending every operation
        // before it starts, so anything non-positive is taken as the opt-out documented in
        // docs/Setup.md. Nothing rejects a negative value, and this is why it need not.
        if ($this->operationTimeout <= 0.0) {
            return $this->runWithoutTimeout($operation);
        }

        $client = $this->client;
        $timeout = $this->operationTimeout;

        /** @var Deferred<T> $failed */
        $failed = new Deferred();
        // What the race cannot say on its own. An exception from the caller's own closure comes out
        // of the same await as a failure of ours, and Bunny re-emits every *channel* error onto the
        // client (Client::channel()), so a client 'error' is not proof that the connection is gone
        // either.
        $failedAsynchronously = false;
        $timedOut = false;
        // Bunny reports asynchronous failures as an 'error' event, and Evenement drops an event
        // nobody listens to - which is how a connection lost mid-operation becomes a promise that
        // stays pending for the rest of the process.
        $onError = static function (Throwable $error) use ($failed, &$failedAsynchronously): void {
            $failedAsynchronously = true;

            $failed->reject($error);
        };
        $timer = Loop::addTimer(
            $timeout,
            static function () use ($failed, $timeout, &$timedOut): void {
                $timedOut = true;

                $failed->reject(OperationFailed::timedOut($timeout));
            },
        );
        $client->on('error', $onError);
        $this->runningOperations++;

        try {
            $result = await(race([async($operation)(), $failed->promise()]));
        } catch (Throwable $exception) {
            // A timed-out operation is still parked somewhere inside the loop, so that client is
            // spent whatever its state says. Otherwise only a connection that is actually gone is
            // worth replacing: Bunny tears the client down itself when the broker closes the
            // connection or the socket dies, which is what leaves it unable to disconnect.
            //
            // Neither an exception from the caller's own closure nor a channel-level error says
            // anything about the connection, and discarding on those costs a reconnect plus
            // whatever was still unflushed elsewhere on it - a channel the broker closed is
            // replaced on its own 'close' event.
            if ($timedOut || ($failedAsynchronously && ! $client->canDisconnect())) {
                $this->discardClient();
            }

            throw $exception;
        } finally {
            $this->runningOperations--;
            Loop::cancelTimer($timer);
            // The client is cached and reused, so a listener left behind would pile up one per
            // operation - on the client this operation ran against, which may no longer be ours.
            $client->removeListener('error', $onError);
        }

        // Whether the operation turned the loop on its way is no help here - opening the channel
        // does, and the publish that follows it is buffered all the same - so the turn comes after
        // the operation, always. Nested calls included: the one they are nested in does turn the
        // loop eventually, but a delivery callback that publishes and then works for a minute holds
        // it for that minute, and a connection lost meanwhile takes the unwritten message with it.
        $this->flushWrites();

        $this->lastOperationAt = microtime(true);

        return $result;
    }

    /**
     * {@inheritDoc}
     *
     * @param Closure(): T $operation
     *
     * @return T
     *
     * @template T
     */
    public function runWithoutTimeout(Closure $operation): mixed
    {
        $this->replaceStaleClient();

        $result = $this->awaitOnItsOwnFiber($operation);

        // As in run(). The consume loop needs it at the very end above all: once the wait is over
        // nothing turns the loop again, and the last thing written is usually an acknowledge.
        $this->flushWrites();

        return $result;
    }

    /**
     * Always on a Fiber of its own, even when one is already current (an acknowledge issued from
     * inside a consumer callback). Running the operation on the calling Fiber instead saves an
     * allocation, but makes PHP unable to switch back out of contexts that forbid it - a signal
     * handler above all - turning a survivable shutdown into a fatal FiberError.
     *
     * @param Closure(): T $operation
     *
     * @return T
     *
     * @template T
     */
    private function awaitOnItsOwnFiber(Closure $operation): mixed
    {
        $this->runningOperations++;

        try {
            $result = await(async($operation)());
        } finally {
            $this->runningOperations--;
        }

        $this->lastOperationAt = microtime(true);

        return $result;
    }

    /**
     * Replaces a connection that has been idle for too long, unless an operation of this
     * connection's own is already in flight: that one is driving the loop, heartbeats are flowing,
     * and pulling its connection away mid-flight is the last thing it needs.
     *
     * Counted rather than read off Fiber::getCurrent(), which only stands for "nested" until the
     * application brings Fibers of its own - anything built on React, or any other Fiber-based
     * runtime. Such a caller is not nested at all, and would have the check skipped for good.
     */
    private function replaceStaleClient(): void
    {
        if ($this->runningOperations > 0 || ! $this->isStale()) {
            return;
        }

        $this->discardClient();
    }

    /**
     * Tears the client down without talking to the broker and replaces it, so that whatever comes
     * next starts from a connection known to be new.
     *
     * RAW_CONNECTION_INACTIVE closes the channels locally and skips the connection.close handshake,
     * so it cannot block on a broker that is already gone. It is also the only path that reaches
     * Bunny's Connection::disconnect(), which cancels the heartbeat timer - the timer that would
     * otherwise keep the event loop, and with it the whole process, alive with no stream left to
     * ever wake it.
     */
    private function discardClient(): void
    {
        if ($this->client->canDisconnect()) {
            try {
                // Unbounded on purpose: there is no round trip to wait for, and abandoning this
                // halfway would leave the heartbeat timer armed - the very thing it is here for.
                $this->awaitOnItsOwnFiber(function (): void {
                    $this->client->disconnect(
                        0,
                        'Connection discarded',
                        ClientInterface::RAW_CONNECTION_INACTIVE,
                    );
                });
            } catch (Throwable) {
                // Nothing useful left to do - the client is being thrown away either way.
            }
        }

        $this->channel = null;
        $this->transactionalChannel = null;
        $this->client = new Client($this->configuration);
        $this->lastOperationAt = null;
    }

    /**
     * Gives the event loop the one iteration it takes to put a write on the socket.
     *
     * publish(), ack() and nack() await no reply, so an operation made of them alone never suspends
     * and the loop never turns - and React only writes its buffer when an iteration finds the socket
     * writable. Bunny awaits a drain of its own once that buffer passes React's soft limit, which is
     * 64 KiB, or a body of 65488 bytes with the smallest framing there is; below that a producer of
     * ordinary messages sends nothing at all. Measured: twelve publishes half a second apart reached
     * the broker as none of them, and with heartbeat=1 the broker hung up on a producer that had
     * been publishing the whole time - while lastOperationAt below was refreshed by every one of
     * them, so the connection never counted as stale either.
     *
     * A timer whose callback asks for a future tick, rather than either on its own: an await that a
     * tick or a timer resolves resumes this Fiber from inside the loop's own tick or timer phase,
     * before it reaches the stream_select() that does the writing. Asking for the tick from the
     * timer leaves that queue non-empty instead, so the loop polls the streams with no timeout on
     * its way to it. Measured, and the reason neither futureTick() nor delay(0) will do here.
     */
    private function flushWrites(): void
    {
        /** @var Deferred<null> $flushed */
        $flushed = new Deferred();

        Loop::addTimer(0.0, static fn () => Loop::futureTick(static fn () => $flushed->resolve(null)));

        // Counted as an operation for the length of it, so that whatever the loop dispatches in that
        // iteration - a delivery callback of the application's own that publishes, say - does not
        // find the connection stale and pull it away from under this one.
        $this->runningOperations++;

        try {
            await($flushed->promise());
        } finally {
            $this->runningOperations--;
        }
    }

    /**
     * Whether the connection has been idle for longer than the heartbeat it promised the broker.
     *
     * Nothing drives the event loop between operations, so a connection idle for that long cannot
     * have sent a single heartbeat frame and the broker has almost certainly closed it. One
     * interval rather than the two the broker waits for is deliberate: reconnecting early costs a
     * handshake, publishing into a dead socket costs the message. The interval is always positive -
     * Configuration\Connection rejects anything else, since Bunny cannot switch heartbeats off.
     */
    private function isStale(): bool
    {
        return $this->lastOperationAt !== null
            && microtime(true) - $this->lastOperationAt >= $this->heartbeat;
    }

    private function createChannel(): ChannelInterface
    {
        $this->connect();

        return $this->client->channel();
    }
}
