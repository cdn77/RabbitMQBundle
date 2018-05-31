<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\RabbitMQ\Operation;

use Cdn77\RabbitMQBundle\Exception\OperationFailed;
use Cdn77\RabbitMQBundle\RabbitMQ\Connection;
use Cdn77\RabbitMQBundle\RabbitMQ\DeliveryMode;
use function array_walk;

final class PublishJsonOperation
{
    private const APPLICATION_JSON = 'application/json';
    private const CONTENT_TYPE = 'content-type';
    private const DELIVERY_MODE = 'delivery-mode';
    private const JSON_HEADERS = [self::CONTENT_TYPE => self::APPLICATION_JSON];
    private const MANDATORY = false;
    private const IMMEDIATE = false;

    /** @var Connection */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function handle(string $json, string $routingKey, string $exchange, bool $persistent = true) : void
    {
        $this->connection->getChannel()->publish(
            $json,
            $this->getHeaders($persistent),
            $exchange,
            $routingKey,
            self::MANDATORY,
            self::IMMEDIATE
        );
    }

    /**
     * @param string[] $jsons
     */
    public function handleAll(array $jsons, string $routingKey, string $exchangeName, bool $persistent = false) : void
    {
        $headers = $this->getHeaders($persistent);

        $transactionalChannel = $this->connection->getTransactionalChannel();
        try {
            array_walk(
                $jsons,
                function (string $json) use ($transactionalChannel, $routingKey, $exchangeName, $headers) : void {
                    $transactionalChannel->publish(
                        $json,
                        $headers,
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

    /**
     * @return mixed[]
     */
    private function getHeaders(bool $persistent) : array
    {
        /** @var mixed[] $headers */
        $headers = self::JSON_HEADERS;
        if ($persistent) {
            $headers[self::DELIVERY_MODE] = DeliveryMode::PERSISTENT;
        }

        return $headers;
    }
}
