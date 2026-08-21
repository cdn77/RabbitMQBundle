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

    /** @return Message[] */
    public function handle(string $queueName, int $maxCount): array
    {
        return $this->connection->run(function () use ($queueName, $maxCount): array {
            $messages = [];
            $channel = $this->connection->getChannel();

            for ($count = 0; $count < $maxCount; $count++) {
                $message = $channel->get($queueName, false);

                if ($message === null) {
                    return $messages;
                }

                $messages[] = $message;
            }

            return $messages;
        });
    }
}
