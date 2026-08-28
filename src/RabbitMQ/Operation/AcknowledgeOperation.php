<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\RabbitMQ\Operation;

use Bunny\Message;
use Cdn77\RabbitMQBundle\RabbitMQ\Connection;

final class AcknowledgeOperation
{
    /** @var Connection */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function handle(Message $message): void
    {
        $this->connection->run(function () use ($message): void {
            $this->connection->getChannel()->ack($message);
        });
    }

    /**
     * RabbitMQ will acknowledge all outstanding delivery tags
     * up to and including the tag specified in the acknowledgement
     */
    public function handleAll(Message $lastMessage): void
    {
        $this->connection->run(function () use ($lastMessage): void {
            $this->connection->getChannel()->ack($lastMessage, true);
        });
    }
}
