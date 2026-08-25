<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\RabbitMQ\Operation;

use Bunny\Message;
use Cdn77\RabbitMQBundle\RabbitMQ\Connection;

final class GetOperation
{
    /** @var Connection */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * A bounded run() per basic.get rather than one around the lot, for the reason SetupAction
     * declares one item at a time: operation_timeout is what a single round trip may take, and
     * every get here is one. Sharing a budget between thousands of them ends a read the broker
     * answered promptly throughout with an OperationFailed - and discards the connection with it.
     * Measured against a real broker at ~0.1ms a get: 5000 of them ran out of a 0.5s bound.
     *
     * @return Message[]
     */
    public function handle(string $queueName, int $maxCount): array
    {
        $messages = [];

        for ($count = 0; $count < $maxCount; $count++) {
            $message = $this->connection->run(
                fn () => $this->connection->getChannel()->get($queueName, false),
            );

            if ($message === null) {
                return $messages;
            }

            $messages[] = $message;
        }

        return $messages;
    }
}
