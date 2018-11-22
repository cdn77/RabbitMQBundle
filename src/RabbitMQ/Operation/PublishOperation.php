<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\RabbitMQ\Operation;

use Cdn77\RabbitMQBundle\Exception\OperationFailed;
use Cdn77\RabbitMQBundle\RabbitMQ\Connection;
use Cdn77\RabbitMQBundle\RabbitMQ\Message;
use function array_walk;

final class PublishOperation
{
    private const MANDATORY = false;
    private const IMMEDIATE = false;

    /**
     * @param mixed[] $headers
     */
    public function handleRaw(
        Connection $connection,
        string $body,
        array $headers,
        string $routingKey,
        string $exchange
    ) : void {
        $connection->getChannel()->publish(
            $body,
            $headers,
            $exchange,
            $routingKey,
            self::MANDATORY,
            self::IMMEDIATE
        );
    }

    public function handle(Connection $connection, Message $message, string $routingKey, string $exchange) : void
    {
        $connection->getChannel()->publish(
            $message->body,
            $message->headers,
            $exchange,
            $routingKey,
            self::MANDATORY,
            self::IMMEDIATE
        );
    }

    /**
     * @param Message[] $messages
     */
    public function handleAll(Connection $connection, array $messages, string $routingKey, string $exchangeName) : void
    {
        $transactionalChannel = $connection->getTransactionalChannel();
        try {
            array_walk(
                $messages,
                static function (Message $message) use ($transactionalChannel, $routingKey, $exchangeName) : void {
                    $transactionalChannel->publish(
                        $message->body,
                        $message->headers,
                        $exchangeName,
                        $routingKey,
                        self::MANDATORY,
                        self::IMMEDIATE
                    );
                }
            );
            $transactionalChannel->txCommit();
        } catch (\Throwable $exception) {
            $transactionalChannel->txRollback();

            throw new OperationFailed(
                $exception->getMessage(),
                $exception->getCode(),
                $exception
            );
        }
    }
}
