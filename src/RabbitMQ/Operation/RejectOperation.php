<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\RabbitMQ\Operation;

use Bunny\Message;
use Cdn77\RabbitMQBundle\RabbitMQ\Connection;

final class RejectOperation
{
    /** @var Connection */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function handle(Message $message, bool $requeue = true) : void
    {
        $channel = $this->connection->getChannel();
        $channel->getClient()->nack(
            $channel->getChannelId(),
            $message->deliveryTag,
            false,
            $requeue
        );
    }

    /**
     * RabbitMQ will reject all outstanding delivery tags
     * up to and including the tag specified in the not nacknowledgement
     */
    public function handleAll(Message $lastMessage, bool $requeue = true) : void
    {
        $channel = $this->connection->getChannel();
        $channel->getClient()->nack(
            $channel->getChannelId(),
            $lastMessage->deliveryTag,
            true,
            $requeue
        );
    }
}
