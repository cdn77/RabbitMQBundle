<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\Tests\RabbitMQ\Consumer;

use Cdn77\RabbitMQBundle\RabbitMQ\Consumer\Configuration;
use PHPUnit\Framework\TestCase;

final class ConfigurationTest extends TestCase
{
    public function testConstruct(): void
    {
        $queueName = 'queue';
        $prefetchCount = 1;
        $prefetchSize = 2;
        $maxMessages = 3;
        $maxSeconds = (float) 4;
        $consumerConfiguration = new Configuration(
            $queueName,
            $prefetchCount,
            $prefetchSize,
            $maxMessages,
            $maxSeconds
        );

        self::assertSame($queueName, $consumerConfiguration->getQueueName());
        self::assertSame($prefetchCount, $consumerConfiguration->getPrefetchCount());
        self::assertSame($prefetchSize, $consumerConfiguration->getPrefetchSize());
        self::assertSame($maxMessages, $consumerConfiguration->getMaxMessages());
        self::assertSame($maxSeconds, $consumerConfiguration->getMaxSeconds());
    }
}
