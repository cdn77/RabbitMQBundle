<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\Tests\RabbitMQ;

use Bunny\Message;
use Cdn77\RabbitMQBundle\RabbitMQ\Consumer\Configuration;
use Cdn77\RabbitMQBundle\RabbitMQ\Consumer\Consumer;
use RuntimeException;

final class ThrowingConsumer implements Consumer
{
    public const string EXCEPTION_MESSAGE = 'Consumer blew up';

    /** @var Configuration */
    private $configuration;

    /** @var int */
    private $consumeCallCount = 0;

    public function __construct(Configuration $configuration)
    {
        $this->configuration = $configuration;
    }

    public function consume(Message $message): void
    {
        $this->consumeCallCount++;

        throw new RuntimeException(self::EXCEPTION_MESSAGE);
    }

    public function getConsumeCallCount(): int
    {
        return $this->consumeCallCount;
    }

    public function getName(): string
    {
        return 'throwing';
    }

    public function getConfiguration(): Configuration
    {
        return $this->configuration;
    }
}
