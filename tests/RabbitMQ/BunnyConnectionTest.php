<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\Tests\RabbitMQ;

use Bunny\Exception\ChannelException;
use Cdn77\RabbitMQBundle\Configuration\Connection as ConnectionConfiguration;
use Cdn77\RabbitMQBundle\Configuration\Dsn;
use Cdn77\RabbitMQBundle\Exception\OperationFailed;
use Cdn77\RabbitMQBundle\RabbitMQ\BunnyConnection;
use Cdn77\RabbitMQBundle\RabbitMQ\Message;
use Cdn77\RabbitMQBundle\RabbitMQ\Operation\GetOperation;
use Cdn77\RabbitMQBundle\RabbitMQ\Operation\PublishOperation;
use Cdn77\RabbitMQBundle\Tests\WithRabbitMQ;
use Fiber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use React\Promise\Promise;
use RuntimeException;

use function getenv;
use function React\Async\async;
use function React\Async\await;
use function React\Async\delay;
use function sleep;

final class BunnyConnectionTest extends TestCase
{
    use WithRabbitMQ;

    private const string QUEUE = 'freshChannelQueue';
    private const string STALE_QUEUE = 'staleConnectionQueue';
    private const string UNRELATED_FIBER_QUEUE = 'unrelatedFiberQueue';
    private const string UNFLUSHED_QUEUE = 'unflushedPublishQueue';
    private const string THROWN_QUEUE = 'operationThrewQueue';

    /** Short enough to keep the suite quick, long enough for the broker to accept it. */
    private const int SHORT_HEARTBEAT = 1;

    /** Generous: the close frame is one round trip away, and a slow runner should not flake. */
    private const float CLOSE_ARRIVAL = 1.0;

    /**
     * A channel the broker closed is unusable - Bunny throws ChannelException('Channel is closed')
     * for every later call on it - so a cached one has to go when it closes, or a single 404 publish
     * would poison the connection for the rest of the process.
     */
    #[Group('Integration')]
    public function testChannelClosedByTheBrokerIsReplaced(): void
    {
        $connection = $this->getConnection();

        $stale = $connection->run(static fn () => $connection->getChannel());

        $this->whenTheBrokerClosesTheChannel($connection);

        // A working channel, not just a different object: the broker answers on it.
        $declareOk = $connection->run(
            static fn () => $connection->getChannel()->queueDeclare(self::QUEUE),
        );
        $connection->run(static fn () => $connection->getChannel()->queueDelete(self::QUEUE));

        self::assertNotSame($stale, $connection->run(static fn () => $connection->getChannel()));
        self::assertSame(self::QUEUE, $declareOk->queue);
    }

    /**
     * The channel.close carries the reason, and Bunny hands it over as an 'error' event that
     * nobody used to listen to - leaving the operation it interrupted to await a reply that was
     * never coming.
     */
    #[Group('Integration')]
    public function testChannelClosedByTheBrokerSurfacesAsAnError(): void
    {
        $connection = $this->getConnection();

        $connection->run(
            static fn () => $connection->getChannel()->publish('body', [], 'noSuchExchange', 'aKey'),
        );

        self::expectException(ChannelException::class);
        self::expectExceptionMessage("NOT_FOUND - no exchange 'noSuchExchange'");

        $connection->run(static fn () => delay(self::CLOSE_ARRIVAL));
    }

    /** @return array<string, array{float, string}> */
    public static function operationTimeoutProvider(): array
    {
        return [
            'a fifth of a second' => [0.2, 'Operation did not finish within 0.2 seconds'],
            // A bound of a fixed number of decimals would report this one as 0.0 seconds, which is
            // the value that turns the bound off.
            'a twenty-fifth of a second' => [0.04, 'Operation did not finish within 0.04 seconds'],
        ];
    }

    /**
     * Nothing in Bunny gives up on a broker that stops answering: every protocol reply is awaited
     * on a promise that only an incoming frame settles. Without a bound of our own, an operation
     * against a dead-but-open socket parks the process for as long as it runs - and the bound it
     * gives up at is the one it reports.
     */
    #[DataProvider('operationTimeoutProvider')]
    public function testOperationThatNeverFinishesTimesOut(float $operationTimeout, string $reported): void
    {
        $connection = new BunnyConnection($this->givenConfiguration(operationTimeout: $operationTimeout));

        self::expectException(OperationFailed::class);
        self::expectExceptionMessage($reported);

        // A promise nobody settles, which is what a lost connection looks like from in here.
        $connection->run(static fn () => await(new Promise(static function (): void {
        })));
    }

    /**
     * The event loop only turns while an operation is awaiting, so a process that publishes and
     * then goes off to do minutes of work of its own cannot send a single heartbeat in between -
     * and the broker hangs up on it. Both messages have to arrive regardless.
     */
    #[Group('Integration')]
    public function testConnectionIdleForLongerThanTheHeartbeatIsReplaced(): void
    {
        $connection = new BunnyConnection($this->givenConfiguration(heartbeat: self::SHORT_HEARTBEAT));

        $publish = new PublishOperation();

        $connection->run(static fn () => $connection->getChannel()->queueDeclare(self::STALE_QUEUE));
        // Transactional, so that a returned call means the broker has the message - no waiting for
        // an asynchronous publish to show up on the queue.
        $publish->handleAll($connection, [Message::json('{"n":1}')], self::STALE_QUEUE, '');

        // Blocking on purpose: this is the frozen loop, not a wait for anything.
        sleep(self::SHORT_HEARTBEAT * 3);

        $publish->handleAll($connection, [Message::json('{"n":2}')], self::STALE_QUEUE, '');

        $messages = (new GetOperation($connection))->handle(self::STALE_QUEUE, 2);

        $connection->run(static fn () => $connection->getChannel()->queueDelete(self::STALE_QUEUE));
        $connection->disconnect();

        self::assertCount(2, $messages);
    }

    /**
     * The same, for a caller that is on a Fiber already - one of the application's own, with no
     * operation of this connection anywhere below it. Whether a call is nested has to be counted:
     * inferred from Fiber::getCurrent() it holds only until something brings Fibers of its own,
     * and anything built on React does, at which point the check above never runs again.
     */
    #[Group('Integration')]
    public function testStaleConnectionIsReplacedForACallerAlreadyOnAFiber(): void
    {
        $connection = new BunnyConnection($this->givenConfiguration(heartbeat: self::SHORT_HEARTBEAT));

        $publish = new PublishOperation();
        $publishSecond = static fn () => $publish->handleAll(
            $connection,
            [Message::json('{"n":2}')],
            self::UNRELATED_FIBER_QUEUE,
            '',
        );

        $connection->run(
            static fn () => $connection->getChannel()->queueDeclare(self::UNRELATED_FIBER_QUEUE),
        );
        $publish->handleAll($connection, [Message::json('{"n":1}')], self::UNRELATED_FIBER_QUEUE, '');

        sleep(self::SHORT_HEARTBEAT * 3);

        await(async($publishSecond)());

        $messages = (new GetOperation($connection))->handle(self::UNRELATED_FIBER_QUEUE, 2);

        $connection->run(
            static fn () => $connection->getChannel()->queueDelete(self::UNRELATED_FIBER_QUEUE),
        );
        $connection->disconnect();

        self::assertCount(2, $messages);
    }

    /**
     * A published message survives the teardown, which is what a producer cares about: closing the
     * socket locally drops whatever React still holds, and this used to lose the message and leave
     * "client unexpectedly closed TCP connection" in the broker log.
     *
     * Two things stand behind it now and this does not tell them apart: run() turns the loop after
     * every operation, so the publish is on the socket before disconnect() is even called, and
     * disconnect() still asks for the connection.close handshake first, which awaits a reply and
     * would flush anything left. The handshake is no longer what saves the message - it is what
     * tells the broker we meant to go.
     */
    #[Group('Integration')]
    public function testDisconnectFlushesAPublishThatHasNotReachedTheSocketYet(): void
    {
        $connection = new BunnyConnection($this->givenConfiguration());
        $reader = $this->getConnection();

        $connection->run(static fn () => $connection->getChannel()->queueDeclare(self::UNFLUSHED_QUEUE));
        // handle(), not handleAll(): a transaction commits, and that round trip would flush the
        // publish on its own.
        (new PublishOperation())->handle($connection, Message::json('{"n":1}'), self::UNFLUSHED_QUEUE, '');
        $connection->disconnect();

        // Read on a connection of its own - the broker's view of what actually arrived.
        $messages = (new GetOperation($reader))->handle(self::UNFLUSHED_QUEUE, 1);

        $reader->run(static fn () => $reader->getChannel()->queueDelete(self::UNFLUSHED_QUEUE));

        self::assertCount(1, $messages);
    }

    /**
     * An exception from the caller's own closure leaves the await by the same door as a connection
     * failure, and used to be taken for one: the client was discarded, which costs a reconnect and
     * - the teardown being local - whatever was still in the write buffer. The connection is not
     * what failed here, so it stays, and so does the publish from before.
     */
    #[Group('Integration')]
    public function testAnOperationThatThrowsOfItsOwnAccordKeepsTheConnection(): void
    {
        $connection = new BunnyConnection($this->givenConfiguration());
        $reader = $this->getConnection();

        $connection->run(static fn () => $connection->getChannel()->queueDeclare(self::THROWN_QUEUE));
        $channel = $connection->run(static fn () => $connection->getChannel());
        (new PublishOperation())->handle($connection, Message::json('{"n":1}'), self::THROWN_QUEUE, '');

        $this->whenTheOperationThrows($connection);

        $channelAfter = $connection->run(static fn () => $connection->getChannel());
        // Whatever is left in the buffer goes out here, as it would at the end of a request.
        $connection->disconnect();

        $messages = (new GetOperation($reader))->handle(self::THROWN_QUEUE, 1);
        $reader->run(static fn () => $reader->getChannel()->queueDelete(self::THROWN_QUEUE));

        self::assertSame($channel, $channelAfter);
        self::assertCount(1, $messages);
    }

    /**
     * The Fiber the operation gets is part of the contract: reusing the calling one - as an
     * acknowledge issued from inside a consumer callback would - leaves PHP unable to switch back
     * out of a context that forbids it, and a shutdown from a signal handler dies with a FiberError
     * instead of just skipping the disconnect. run() itself talks to nobody, so no broker needed.
     */
    public function testRunGivesEveryOperationAFiberOfItsOwn(): void
    {
        $connection = new BunnyConnection(
            ConnectionConfiguration::fromDsn(new Dsn('amqp://127.0.0.1/')),
        );

        $callingFiber = Fiber::getCurrent();
        $outer = null;
        $inner = null;

        $connection->run(static function () use ($connection, &$outer, &$inner): void {
            $outer = Fiber::getCurrent();

            $connection->run(static function () use (&$inner): void {
                $inner = Fiber::getCurrent();
            });
        });

        self::assertInstanceOf(Fiber::class, $outer);
        self::assertInstanceOf(Fiber::class, $inner);
        self::assertNotSame($callingFiber, $outer);
        self::assertNotSame($outer, $inner);
    }

    protected function tearDown(): void
    {
        $this->getConnection()->disconnect();
    }

    /** The broker this suite runs against, with the timings a single test needs. */
    private function givenConfiguration(
        int $heartbeat = 60,
        float $operationTimeout = 30.0,
    ): ConnectionConfiguration {
        $dsn = new Dsn((string) getenv('RABBITMQ_DSN'));

        return new ConnectionConfiguration(
            $dsn->getHost(),
            $dsn->getPort(),
            $dsn->getVhost(),
            $dsn->getUsername(),
            $dsn->getPassword(),
            $heartbeat,
            operationTimeout: $operationTimeout,
        );
    }

    /**
     * RabbitMQ answers a publish to an exchange that does not exist by closing the channel. The
     * failure surfaces on whatever operation comes next - here, only a wait for it to arrive -
     * which is what testChannelClosedByTheBrokerSurfacesAsAnError() is about.
     */

    /** The failure itself is nothing to do with the broker, and is asserted on in its own right. */
    private function whenTheOperationThrows(BunnyConnection $connection): void
    {
        try {
            $connection->run(static function (): void {
                throw new RuntimeException('a failure of the caller\'s own');
            });
        } catch (RuntimeException) {
            // Expected: it is the connection afterwards that this is about.
        }
    }

    private function whenTheBrokerClosesTheChannel(BunnyConnection $connection): void
    {
        $connection->run(
            static fn () => $connection->getChannel()->publish('body', [], 'noSuchExchange', 'aKey'),
        );

        try {
            $connection->run(static fn () => delay(self::CLOSE_ARRIVAL));
        } catch (ChannelException) {
            // Expected, and asserted on its own elsewhere.
        }
    }
}
