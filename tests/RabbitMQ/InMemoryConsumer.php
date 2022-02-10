<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\Tests\RabbitMQ;

use Bunny\Message;
use Cdn77\RabbitMQBundle\RabbitMQ\Consumer\Configuration;
use Cdn77\RabbitMQBundle\RabbitMQ\Consumer\Consumer;
use Cdn77\RabbitMQBundle\RabbitMQ\Operation\AcknowledgeOperation;

final class InMemoryConsumer implements Consumer
{
    /** @var AcknowledgeOperation */
    private $acknowledgeOperation;

    /** @var Configuration */
    private $configuration;

    /** @var Message[] */
    private $consumedMessages = [];

    public function __construct(AcknowledgeOperation $acknowledgeOperation, Configuration $configuration)
    {
        $this->acknowledgeOperation = $acknowledgeOperation;
        $this->configuration = $configuration;
    }

    public function consume(Message $message): void
    {
        $this->consumedMessages[] = $message;

        $this->acknowledgeOperation->handle($message);
    }

    public function getName(): string
    {
        return 'test';
    }

    public function getConfiguration(): Configuration
    {
        return $this->configuration;
    }

    /** @return Message[] */
    public function getConsumedMessages(): array
    {
        return $this->consumedMessages;
    }
}
