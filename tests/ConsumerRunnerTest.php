<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\Tests;

use Cdn77\RabbitMQBundle\Configuration\Topology;
use Cdn77\RabbitMQBundle\RabbitMQ\Binding;
use Cdn77\RabbitMQBundle\RabbitMQ\Consumer\Configuration;
use Cdn77\RabbitMQBundle\RabbitMQ\Consumer\Consumer;
use Cdn77\RabbitMQBundle\RabbitMQ\Exchange;
use Cdn77\RabbitMQBundle\RabbitMQ\ExchangeType;
use Cdn77\RabbitMQBundle\RabbitMQ\Operation\AcknowledgeOperation;
use Cdn77\RabbitMQBundle\RabbitMQ\Queue;
use Cdn77\RabbitMQBundle\Tests\RabbitMQ\InMemoryConsumer;
use PHPUnit\Framework\TestCase;
use function end;

/**
 * @group Integration
 */
final class ConsumerRunnerTest extends TestCase
{
    use WithRabbitMQ;

    /**
     * @dataProvider maxMessagesDataProvider
     */
    public function testMaxMessagesLimit(int $maxMessages) : void
    {
        $exchange = new Exchange('test', ExchangeType::get(ExchangeType::DIRECT));
        $queue = new Queue('testQueue');
        $routingKey = 'a_routing_key';
        $topology = new Topology(
            [$exchange],
            [],
            [$queue],
            [$queue->getName() => [new Binding($exchange, $routingKey)]]
        );
        $this->setupTopology($topology);

        $this->givenEnoughMessagesInQueue($exchange, $routingKey);
        $consumer = $this->givenConfiguredConsumer($maxMessages, $queue);

        $this->whenConsume($consumer);

        $this->thenOnlyMaxMessagesCountIsConsumed($maxMessages, $consumer);
    }

    /**
     * @return int[][]
     */
    public function maxMessagesDataProvider() : array
    {
        return [[0], [5]];
    }

    private function givenEnoughMessagesInQueue(Exchange $exchange, string $routingKey) : void
    {
        $channel = $this->getConnection()->getChannel();
        for ($i = 1; $i <= 10; $i++) {
            $channel->publish((string) $i, [], $exchange->getName(), $routingKey);
        }
    }

    private function givenConfiguredConsumer(int $maxMessages, Queue $queue) : InMemoryConsumer
    {
        $consumer = new InMemoryConsumer(
            new AcknowledgeOperation($this->getConnection()),
            new Configuration($queue->getName(), 1, 0, $maxMessages)
        );

        return $consumer;
    }

    private function whenConsume(Consumer $consumer) : void
    {
        $this->getConsumerRunner()->run($consumer);
    }

    private function thenOnlyMaxMessagesCountIsConsumed(int $maxMessages, InMemoryConsumer $consumer) : void
    {
        $consumedMessages = $consumer->getConsumedMessages();
        self::assertCount($maxMessages, $consumedMessages);

        if ($maxMessages === 0) {
            return;
        }

        self::assertSame((string) $maxMessages, end($consumedMessages)->content);
    }
}
