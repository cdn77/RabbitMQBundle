<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\RabbitMQ\Operation;

use Cdn77\RabbitMQBundle\Exception\Exception as BundleException;
use Cdn77\RabbitMQBundle\Exception\OperationFailed;
use Cdn77\RabbitMQBundle\RabbitMQ\Connection;
use Cdn77\RabbitMQBundle\RabbitMQ\Message;
use Throwable;

final class PublishOperation
{
    private const bool MANDATORY = false;
    private const bool IMMEDIATE = false;

    /** @param array<string, mixed> $headers */
    public function handleRaw(
        Connection $connection,
        string $body,
        array $headers,
        string $routingKey,
        string $exchange,
    ): void {
        $connection->run(static function () use ($connection, $body, $headers, $routingKey, $exchange): void {
            $connection->getChannel()->publish(
                $body,
                $headers,
                $exchange,
                $routingKey,
                self::MANDATORY,
                self::IMMEDIATE,
            );
        });
    }

    public function handle(Connection $connection, Message $message, string $routingKey, string $exchange): void
    {
        $connection->run(static function () use ($connection, $message, $routingKey, $exchange): void {
            $connection->getChannel()->publish(
                $message->body,
                $message->headers,
                $exchange,
                $routingKey,
                self::MANDATORY,
                self::IMMEDIATE,
            );
        });
    }

    /**
     * The wrapping sits around run(), not inside the operation, because the failure does not
     * always come back through the operation's own Fiber. A broker that closes the channel gets
     * the rollback attempt below as far as awaiting its tx.rollback-ok, and that suspension hands
     * the read loop the very channel.close that caused all this: it reaches the channel, whose
     * 'error' the client re-emits, and run() then loses the race to a Fiber that is still parked in
     * the rollback - which is where the wrapper used to be. Verified against a real broker: a batch
     * published to a missing exchange leaked Bunny's ChannelException.
     *
     * @param Message[] $messages
     */
    public function handleAll(
        Connection $connection,
        iterable $messages,
        string $routingKey,
        string $exchangeName,
    ): void {
        try {
            $connection->run(static function () use ($connection, $messages, $routingKey, $exchangeName): void {
                $transactionalChannel = $connection->getTransactionalChannel();
                try {
                    foreach ($messages as $message) {
                        $transactionalChannel->publish(
                            $message->body,
                            $message->headers,
                            $exchangeName,
                            $routingKey,
                            self::MANDATORY,
                            self::IMMEDIATE,
                        );
                    }

                    $transactionalChannel->txCommit();
                } catch (Throwable $exception) {
                    try {
                        $transactionalChannel->txRollback();
                    } catch (Throwable) {
                        // The usual cause of the failure above is the broker closing the channel,
                        // and such a channel can no longer be rolled back - nor does it need to be.
                        // Keep reporting what actually went wrong.
                    }

                    throw $exception;
                }
            });
        } catch (BundleException $exception) {
            // Already ours, and more specific than this operation could be: a connection that could
            // not be established, or the timeout that ended the commit.
            throw $exception;
        } catch (Throwable $exception) {
            throw new OperationFailed(
                $exception->getMessage(),
                $exception->getCode(),
                $exception,
            );
        }
    }
}
