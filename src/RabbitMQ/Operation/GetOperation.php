<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\RabbitMQ\Operation;

use Bunny\Message;
use Cdn77\RabbitMQBundle\Exception\OperationFailed;
use Cdn77\RabbitMQBundle\RabbitMQ\Connection;
use React\Promise\PromiseInterface;

final class GetOperation
{
    /** @var Connection */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /** @return Message[] */
    public function handle(string $queueName, int $maxCount): array
    {
        $messages = [];

        for ($count = 0; $count < $maxCount; $count++) {
            /** @var Message|PromiseInterface|null $message */
            $message = $this->connection->getChannel()->get($queueName, false);

            if ($message === null) {
                return $messages;
            }

            if ($message instanceof PromiseInterface) {
                throw OperationFailed::gotInvalidType(Message::class, PromiseInterface::class);
            }

            $messages[] = $message;
        }

        return $messages;
    }
}
