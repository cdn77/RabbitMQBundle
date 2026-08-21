<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\RabbitMQ\Operation;

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

    /** @param Message[] $messages */
    public function handleAll(
        Connection $connection,
        iterable $messages,
        string $routingKey,
        string $exchangeName,
    ): void {
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
                    // The usual cause of the failure above is the broker closing the channel, and
                    // such a channel can no longer be rolled back - nor does it need to be. Keep
                    // reporting what actually went wrong.
                }

                throw new OperationFailed(
                    $exception->getMessage(),
                    $exception->getCode(),
                    $exception,
                );
            }
        });
    }
}
