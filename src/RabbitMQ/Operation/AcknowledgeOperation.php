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

    public function handle(Message $message) : void
    {
        $channel = $this->connection->getChannel();
        $channel->getClient()->ack(
            $channel->getChannelId(),
            $message->deliveryTag,
            false
        );
    }

    /**
     * RabbitMQ will acknowledge all outstanding delivery tags up to and including the tag specified in the acknowledgement
     */
    public function handleAll(Message $lastMessage) : void
    {
        $channel = $this->connection->getChannel();
        $channel->getClient()->ack(
            $channel->getChannelId(),
            $lastMessage->deliveryTag,
            true
        );
    }
}
