<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle;

use Bunny\ChannelInterface;
use Bunny\Message;
use Cdn77\RabbitMQBundle\Exception\ConfigurationFailed;
use Cdn77\RabbitMQBundle\Exception\ConnectionFailed;
use Cdn77\RabbitMQBundle\RabbitMQ\Connection;
use Cdn77\RabbitMQBundle\RabbitMQ\Consumer\Consumer;
use React\EventLoop\Loop;
use React\Promise\Deferred;
use Throwable;

use function React\Async\async;
use function React\Async\await;

final class ConsumerRunner
{
    /** @var Connection */
    private $connection;

    /** @var int */
    private $processedMessageCount = 0;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function run(Consumer $consumer): void
    {
        $configuration = $consumer->getConfiguration();
        $this->processedMessageCount = 0;

        if (! $this->hasAnyMessageLeft($configuration->getMaxMessages(), $this->processedMessageCount)) {
            return;
        }

        // The one caller that must not be bounded by the operation timeout: waiting for messages
        // ends when the consumer's message or time limit is reached, which is minutes or hours from
        // here. Getting there is bounded all the same - see the startup below.
        $this->connection->runWithoutTimeout(function () use ($consumer, $configuration): void {
            /** @var Deferred<null> $stopped */
            $stopped = new Deferred();
            $stopping = false;
            // Written by the delivery callback and read by the closures below, which phpstan cannot
            // see through a by-reference binding: without the annotations it narrows both to false.
            /** @var bool $handling */
            $handling = false;
            /** @var bool $stopWhenHandled */
            $stopWhenHandled = false;

            // Settle on a future tick, never straight from a delivery callback: doing it inline
            // would resume the Fiber awaiting below from inside the callback's own Fiber, leaving
            // React's scheduler with no way to hand the result back to run()'s caller. The flag has
            // to be set right away though: Bunny hands over the next buffered delivery as soon as
            // the callback returns, long before a future tick gets to run.
            //
            // And not while a message is being handled. A handler that awaits anything - a publish
            // that commits, a get, any round trip - suspends its own Fiber and turns the loop, which
            // is where the time limit falls due: ending the run there would have run() return, the
            // consumer cancelled and the connection closed by kernel/console.terminate while that
            // handler was still parked. It then resumes into a connection that is gone, so its
            // acknowledge is lost and the message redelivered, and should it throw, the rejection
            // lands on a promise that settled long ago and is dropped - the command exiting
            // successfully having half-processed a message. The last thing the handler does is
            // settle this instead. Only ever one of them: Bunny queues deliveries and runs them
            // with a concurrency of 1.
            $stop = static function () use (&$stopping, &$handling, &$stopWhenHandled, $stopped): void {
                $stopping = true;

                if ($handling) {
                    $stopWhenHandled = true;

                    return;
                }

                Loop::futureTick(static fn () => $stopped->resolve(null));
            };
            // A failure is not held back for the handler, unlike the stop above: it comes from a
            // channel that errored or closed, and a handler parked in a round trip on a connection
            // that is gone never resumes at all - Bunny settles a protocol wait from an incoming
            // frame and rejects none of them when the socket dies. Waiting for it would block the
            // run for good, which is the failure this whole class is here to avoid.
            $fail = static function (Throwable $error) use (&$stopping, $stopped): void {
                $stopping = true;

                Loop::futureTick(static fn () => $stopped->reject($error));
            };
            // A broker-closed channel emits both events: 'close' first, then 'error' carrying a
            // ChannelException with the reply code and text. Only the first rejection counts, so
            // hold this fallback back by a tick and let the one that says why go first. Nothing
            // else settles the promise when the channel goes without an error - a lost connection
            // closes it silently - so the fallback still gets there.
            $closed = static fn () => Loop::futureTick(
                static fn () => $fail(ConnectionFailed::channelClosedWithoutAnError()),
            );

            // Bounded, unlike the wait for messages that follows it: opening the channel,
            // basic.qos and basic.consume each wait for the broker to answer, and Bunny leaves
            // such a wait pending for good when the socket dies - only an incoming frame ever
            // settles one. The handlers below are no help here however early they are installed:
            // a Fiber stuck in the handshake never reaches the await() they reject.
            [$channel, $consumerTag] = $this->connection->run(
                /** @return array{ChannelInterface, string} */
                function () use (
                    $consumer,
                    $configuration,
                    $stop,
                    $fail,
                    $closed,
                    $stopped,
                    &$stopping,
                    &$handling,
                    &$stopWhenHandled,
                ): array {
                    $channel = $this->connection->getChannel();

                    try {
                        $channel->qos($configuration->getPrefetchSize(), $configuration->getPrefetchCount());
                    } catch (Throwable $error) {
                        throw ConfigurationFailed::invalidPrefetchValues($error);
                    }

                    $consumeOk = $channel->consume(
                        async(function (Message $message) use (
                            $consumer,
                            $configuration,
                            $channel,
                            $stop,
                            $fail,
                            $stopped,
                            &$stopping,
                            &$handling,
                            &$stopWhenHandled,
                        ): void {
                            $handling = true;

                            try {
                                // Further messages may already be in flight (prefetch) once the
                                // run is over - be it the limit being reached or the consumer
                                // having failed. Reject the ones we still get handed rather than
                                // only skipping them, so they go back to the queue right away
                                // instead of sitting unacknowledged - and invisible to other
                                // consumers - until the connection goes away. Whatever Bunny has
                                // buffered but not yet delivered is dropped by basic.cancel below
                                // and only the broker can put those back, which it does once this
                                // channel or connection closes.
                                if (
                                    $stopping
                                    || ! $this->hasAnyMessageLeft(
                                        $configuration->getMaxMessages(),
                                        $this->processedMessageCount,
                                    )
                                ) {
                                    $channel->nack($message, false, true);

                                    return;
                                }

                                $consumer->consume($message);

                                $this->processedMessageCount++;

                                if (
                                    $this->hasAnyMessageLeft(
                                        $configuration->getMaxMessages(),
                                        $this->processedMessageCount,
                                    )
                                ) {
                                    return;
                                }

                                $stop();
                            } catch (Throwable $error) {
                                // The callback runs in its own Fiber, so throwing here would only
                                // surface as an unhandled promise rejection. Hand the failure to
                                // the awaited promise instead to let it propagate out of run().
                                $fail($error);
                            } finally {
                                $handling = false;

                                // The run was told to end while this handler had the loop: settling
                                // is its to do, now that it is finished with the message. A no-op
                                // if the failure above got there first.
                                if ($stopWhenHandled) {
                                    Loop::futureTick(static fn () => $stopped->resolve(null));
                                }
                            }
                        }),
                        $configuration->getQueueName(),
                    );

                    // Bunny reports channel and connection level failures as 'error'/'close'
                    // events rather than by throwing, and an unlistened event is silently dropped.
                    // Without these the await() below would keep blocking after e.g. the broker
                    // closed the channel or the connection was lost. Registered only now that
                    // consume() is through - it reports its own failures by throwing - and still
                    // in here, where no tick can pass between the two.
                    $channel->on('error', $fail);
                    $channel->once('close', $closed);

                    return [$channel, $consumeOk->consumerTag];
                },
            );

            $maxSeconds = $configuration->getMaxSeconds();
            $timer = $maxSeconds !== null ? Loop::addTimer($maxSeconds, $stop) : null;
            $stoppedCleanly = false;

            try {
                await($stopped->promise());

                $stoppedCleanly = true;
            } finally {
                if ($timer !== null) {
                    Loop::cancelTimer($timer);
                }

                // The channel is cached and reused, so leave neither this run's listeners nor its
                // consumer behind on it - the latter would keep delivering into a callback whose
                // promise is already settled, silently swallowing those messages.
                $channel->removeListener('error', $fail);
                $channel->removeListener('close', $closed);

                try {
                    // No-wait, so that losing the connection right here cannot leave this blocked
                    // for good: Bunny does not reject a pending protocol wait when the socket goes
                    // away, and the promise that watched for channel failures has settled already.
                    // Nothing depends on the broker's confirmation anyway - Bunny stops delivering
                    // for this consumer tag the moment cancel() returns, and messages the broker
                    // still sends until it processes the frame are requeued when the channel or the
                    // connection closes.
                    $channel->cancel($consumerTag, true);
                } catch (Throwable $error) {
                    // A channel the broker already closed can be neither used nor cancelled, and
                    // saying so must not bury the failure that ended the run.
                    if ($stoppedCleanly) {
                        throw $error;
                    }
                }
            }
        });
    }

    private function hasAnyMessageLeft(int|null $maxMessages, int $processedMessageCount): bool
    {
        return $maxMessages === null || $processedMessageCount < $maxMessages;
    }
}
