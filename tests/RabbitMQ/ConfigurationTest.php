<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\Tests\RabbitMQ;

use Cdn77\RabbitMQBundle\Configuration\Connection;
use Cdn77\RabbitMQBundle\Configuration\Topology;
use Cdn77\RabbitMQBundle\DependencyInjection\RabbitMQExtension;
use Cdn77\RabbitMQBundle\RabbitMQ\ExchangeType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;
use function file_get_contents;

final class ConfigurationTest extends TestCase
{
    public function testConnectionConfiguration() : void
    {
        $config = Yaml::parse(file_get_contents(__DIR__ . '/ConfigurationTest.yaml'));
        $rabbitConfiguration = $config[RabbitMQExtension::ALIAS];
        $configuration = Connection::fromDI($rabbitConfiguration);

        self::assertSame('127.0.0.1', $configuration->getHost());
        self::assertSame(5672, $configuration->getPort());
        self::assertSame('/', $configuration->getVhost());
        self::assertSame('guest', $configuration->getUser());
        self::assertSame('password', $configuration->getPassword());
        self::assertSame(60, $configuration->getHeartbeat());
        self::assertSame(10, $configuration->getConnectionTimeout());
        self::assertSame(11, $configuration->getReadWriteTimeout());
    }

    public function testTopologyConfiguration() : void
    {
        $config = Yaml::parse(file_get_contents(__DIR__ . '/ConfigurationTest.yaml'));
        $rabbitConfiguration = $config[RabbitMQExtension::ALIAS];
        $configuration = Topology::fromDI($rabbitConfiguration);

        self::assertCount(2, $configuration->getExchanges());

        self::assertArrayHasKey('exchange1', $configuration->getExchanges());
        $exchange1 = $configuration->getExchanges()['exchange1'];
        self::assertTrue($exchange1->isDurable());
        self::assertSame(ExchangeType::TOPIC, $exchange1->getExchangeType()->getValue());
        self::assertEmpty($exchange1->getBindings());

        self::assertArrayHasKey('exchange2', $configuration->getExchanges());
        $exchange2 = $configuration->getExchanges()['exchange2'];
        self::assertFalse($exchange2->isDurable());
        self::assertSame(ExchangeType::DIRECT, $exchange2->getExchangeType()->getValue());

        self::assertCount(1, $exchange2->getBindings());
        $binding = $exchange2->getBindings()[0];
        self::assertSame($exchange1, $binding->getBindable());
        self::assertSame('routing_key1', $binding->getRoutingKey());

        self::assertCount(1, $configuration->getQueues());
        $queue = $configuration->getQueues()['queue1'];
        self::assertTrue($queue->isDurable());

        self::assertCount(1, $queue->getBindings());
        $binding = $queue->getBindings()[0];
        self::assertSame($exchange1, $binding->getBindable());
        self::assertSame('queue1', $binding->getRoutingKey());
    }
}
