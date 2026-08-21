<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\Tests;

use Bunny\Message;
use Cdn77\RabbitMQBundle\Configuration\Topology;
use Cdn77\RabbitMQBundle\RabbitMQ\Binding;
use Cdn77\RabbitMQBundle\RabbitMQ\Consumer\Configuration;
use Cdn77\RabbitMQBundle\RabbitMQ\Consumer\Consumer;
use Cdn77\RabbitMQBundle\RabbitMQ\Exchange;
use Cdn77\RabbitMQBundle\RabbitMQ\ExchangeType;
use Cdn77\RabbitMQBundle\RabbitMQ\Operation\AcknowledgeOperation;
use Cdn77\RabbitMQBundle\RabbitMQ\Queue;
use Cdn77\RabbitMQBundle\Tests\RabbitMQ\InMemoryConsumer;
use Cdn77\RabbitMQBundle\Tests\RabbitMQ\ThrowingConsumer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function end;

#[Group('Integration')]
final class ConsumerRunnerTest extends TestCase
{
    use WithRabbitMQ;

    /** @return int[][] */
    public static function maxMessagesDataProvider(): array
    {
        return [[0], [5]];
    }

    public function setUp(): void
    {
        $this->clearRabbitMQ();

        parent::setUp();
    }

    public function tearDown(): void
    {
        $this->clearRabbitMQ();
        $this->getConnection()->disconnect();

        parent::tearDown();
    }

    #[DataProvider('maxMessagesDataProvider')]
    public function testMaxMessagesLimit(int $maxMessages): void
    {
        $queue = $this->givenQueueWithEnoughMessages();
        $consumer = $this->givenConfiguredConsumer($maxMessages, $queue);

        $this->whenConsume($consumer);

        $this->thenOnlyMaxMessagesCountIsConsumed($maxMessages, $consumer);
    }

    public function testConsumerExceptionIsPropagated(): void
    {
        $queue = $this->givenQueueWithEnoughMessages();

        // The consumer callback runs in its own Fiber, so an exception thrown there can only end
        // up as an unhandled promise rejection unless the runner routes it out of run(). maxSeconds
        // is a safety net: without it a regression would block the suite instead of failing it.
        $consumer = new ThrowingConsumer(new Configuration($queue->getName(), 1, 0, null, 5.0));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(ThrowingConsumer::EXCEPTION_MESSAGE);

        $this->whenConsume($consumer);
    }

    public function testConsumerIsNotCalledAgainAfterItFailed(): void
    {
        $queue = $this->givenQueueWithEnoughMessages();

        // Prefetch more than one message, so Bunny has further deliveries buffered by the time the
        // first one fails.
        $consumer = new ThrowingConsumer(new Configuration($queue->getName(), 10, 0, null, 5.0));

        try {
            $this->whenConsume($consumer);

            self::fail('The consumer exception should have been propagated');
        } catch (RuntimeException $error) {
            self::assertSame(ThrowingConsumer::EXCEPTION_MESSAGE, $error->getMessage());
        }

        // The failure settles the awaited promise only on a future tick, while Bunny hands over the
        // next buffered delivery as soon as the callback returns - a consumer that has just failed
        // must not be given those, they belong back in the queue.
        self::assertSame(1, $consumer->getConsumeCallCount());
    }

    private function clearRabbitMQ(): void
    {
        $connection = $this->getConnection();
        $connection->run(static function () use ($connection): void {
            $connection->getChannel()->queueDelete('testQueue');
            $connection->getChannel()->exchangeDelete('test');
        });
    }

    private function givenQueueWithEnoughMessages(): Queue
    {
        $exchange = new Exchange('test', new ExchangeType(ExchangeType::DIRECT));
        $queue = new Queue('testQueue');
        $routingKey = 'a_routing_key';
        $topology = new Topology(
            [$exchange],
            [],
            [$queue],
            [$queue->getName() => [new Binding($exchange, $routingKey)]],
        );
        $this->setupTopology($topology);

        $this->givenEnoughMessagesInQueue($exchange, $routingKey);

        return $queue;
    }

    private function givenEnoughMessagesInQueue(Exchange $exchange, string $routingKey): void
    {
        $connection = $this->getConnection();
        $connection->run(static function () use ($connection, $exchange, $routingKey): void {
            $channel = $connection->getChannel();
            for ($i = 1; $i <= 10; $i++) {
                $channel->publish((string) $i, [], $exchange->getName(), $routingKey);
            }
        });
    }

    private function givenConfiguredConsumer(int $maxMessages, Queue $queue): InMemoryConsumer
    {
        return new InMemoryConsumer(
            new AcknowledgeOperation($this->getConnection()),
            new Configuration($queue->getName(), 1, 0, $maxMessages),
        );
    }

    private function whenConsume(Consumer $consumer): void
    {
        $this->getConsumerRunner()->run($consumer);
    }

    private function thenOnlyMaxMessagesCountIsConsumed(int $maxMessages, InMemoryConsumer $consumer): void
    {
        $consumedMessages = $consumer->getConsumedMessages();
        self::assertCount($maxMessages, $consumedMessages);

        if ($maxMessages === 0) {
            return;
        }

        $bunnyMessage = end($consumedMessages);
        self::assertInstanceOf(Message::class, $bunnyMessage);
        self::assertSame((string) $maxMessages, $bunnyMessage->content);
    }
}
