<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle;

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

        $this->connection->run(function () use ($consumer, $configuration): void {
            $channel = $this->connection->getChannel();

            try {
                $channel->qos($configuration->getPrefetchSize(), $configuration->getPrefetchCount());
            } catch (Throwable $error) {
                throw ConfigurationFailed::invalidPrefetchValues($error);
            }

            /** @var Deferred<null> $stopped */
            $stopped = new Deferred();
            $stopping = false;

            // Settle on a future tick, never straight from a delivery callback: doing it inline
            // would resume the Fiber awaiting below from inside the callback's own Fiber, leaving
            // React's scheduler with no way to hand the result back to run()'s caller. The flag has
            // to be set right away though: Bunny hands over the next buffered delivery as soon as
            // the callback returns, long before a future tick gets to run.
            $stop = static function () use (&$stopping, $stopped): void {
                $stopping = true;

                Loop::futureTick(static fn () => $stopped->resolve(null));
            };
            $fail = static function (Throwable $error) use (&$stopping, $stopped): void {
                $stopping = true;

                Loop::futureTick(static fn () => $stopped->reject($error));
            };

            $consumeOk = $channel->consume(
                async(function (Message $message) use (
                    $consumer,
                    $configuration,
                    $channel,
                    $stop,
                    $fail,
                    &$stopping,
                ): void {
                    try {
                        // Further messages may already be in flight (prefetch) once the run is
                        // over - be it the limit being reached or the consumer having failed.
                        // Reject the ones we still get handed rather than only skipping them, so
                        // they go back to the queue right away instead of sitting unacknowledged -
                        // and invisible to other consumers - until the connection goes away.
                        // Whatever Bunny has buffered but not yet delivered is dropped by
                        // basic.cancel below and only the broker can put those back, which it does
                        // once this channel or connection closes.
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
                        // The callback runs in its own Fiber, so throwing here would only surface
                        // as an unhandled promise rejection. Hand the failure to the awaited
                        // promise instead to let it propagate out of run().
                        $fail($error);
                    }
                }),
                $configuration->getQueueName(),
            );

            // Bunny reports channel and connection level failures as 'error'/'close' events rather
            // than by throwing, and an unlistened event is silently dropped. Without these the
            // await() below would keep blocking after e.g. the broker closed the channel or the
            // connection was lost. Registered only now that consume() is through - it reports its
            // own failures by throwing, and a rejection here would have nothing awaiting it yet.
            // A broker-closed channel emits both events: 'close' first, then 'error' carrying a
            // ChannelException with the reply code and text. Only the first rejection counts, so
            // hold this fallback back by a tick and let the one that says why go first. Nothing
            // else settles the promise when the channel goes without an error - a lost connection
            // closes it silently - so the fallback still gets there.
            $closed = static fn () => Loop::futureTick(
                static fn () => $fail(ConnectionFailed::channelClosed()),
            );
            $channel->on('error', $fail);
            $channel->once('close', $closed);

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
                    $channel->cancel($consumeOk->consumerTag, true);
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
