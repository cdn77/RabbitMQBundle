<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\Tests;

use Bunny\Message;
use Cdn77\RabbitMQBundle\Configuration\Connection;
use Cdn77\RabbitMQBundle\Configuration\Dsn;
use Cdn77\RabbitMQBundle\Configuration\Topology;
use Cdn77\RabbitMQBundle\ConsumerRunner;
use Cdn77\RabbitMQBundle\Exception\ConfigurationFailed;
use Cdn77\RabbitMQBundle\RabbitMQ\Binding;
use Cdn77\RabbitMQBundle\RabbitMQ\BunnyConnection;
use Cdn77\RabbitMQBundle\RabbitMQ\Consumer\Configuration;
use Cdn77\RabbitMQBundle\RabbitMQ\Consumer\Consumer;
use Cdn77\RabbitMQBundle\RabbitMQ\Exchange;
use Cdn77\RabbitMQBundle\RabbitMQ\ExchangeType;
use Cdn77\RabbitMQBundle\RabbitMQ\Operation\AcknowledgeOperation;
use Cdn77\RabbitMQBundle\RabbitMQ\Queue;
use Cdn77\RabbitMQBundle\Tests\RabbitMQ\InMemoryConsumer;
use Cdn77\RabbitMQBundle\Tests\RabbitMQ\SuspendingConsumer;
use Cdn77\RabbitMQBundle\Tests\RabbitMQ\ThrowingConsumer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function assert;
use function end;
use function fgets;
use function getenv;
use function is_string;
use function microtime;
use function proc_close;
use function proc_open;
use function proc_terminate;
use function stream_get_contents;
use function usleep;

use const PHP_BINARY;

#[Group('Integration')]
final class ConsumerRunnerTest extends TestCase
{
    use WithRabbitMQ;

    private const float SHORT_OPERATION_TIMEOUT = 0.2;
    private const float CONSUMING_SECONDS = 1.0;
    private const float STOP_AFTER_SECONDS = 0.3;
    private const float SUSPEND_FOR_SECONDS = 1.0;
    private const string HANDLER_SOURCE_QUEUE = 'consumerRunnerTestSourceQueue';
    private const string HANDLER_TARGET_QUEUE = 'consumerRunnerTestTargetQueue';
    private const int SIGKILL = 9;
    private const float BROKER_SECONDS = 3.0;
    private const int POLL_MICROSECONDS = 50000;

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

    /**
     * The consume loop is the one operation that must not be bounded: it returns when the
     * consumer's own message or time limit says so, which is well past any per-operation timeout.
     * Bounding it would end every consumer with an OperationFailed instead.
     */
    public function testConsumingIsNotBoundedByTheOperationTimeout(): void
    {
        $exchange = new Exchange('test', new ExchangeType(ExchangeType::DIRECT));
        $queue = $this->givenEmptyQueue($exchange, 'a_routing_key');

        // An operation timeout far shorter than the time the consumer is told to wait for messages
        // that are never going to come.
        $connection = new BunnyConnection(Connection::fromDsn(
            new Dsn(self::dsn() . '&operation_timeout=' . self::SHORT_OPERATION_TIMEOUT),
        ));
        $consumer = new InMemoryConsumer(
            new AcknowledgeOperation($connection),
            new Configuration($queue->getName(), 1, 0, null, self::CONSUMING_SECONDS),
        );

        $startedAt = microtime(true);
        (new ConsumerRunner($connection))->run($consumer);
        $consumingTook = microtime(true) - $startedAt;

        $connection->disconnect();

        self::assertGreaterThanOrEqual(self::CONSUMING_SECONDS, $consumingTook);
        self::assertCount(0, $consumer->getConsumedMessages());
    }

    /**
     * The prefetch failure as a real broker reports it, which the unit test's throwing channel
     * cannot stand in for: RabbitMQ answers a non-zero prefetch-size with a connection.close, and
     * that frame is dispatched twice - Bunny's await list rejects the pending basic.qos-ok with it
     * first (resuming this operation's Fiber from inside the read loop, so the translation below
     * happens), and only then does it reach the channel, which emits 'close' and an 'error' the
     * client re-emits. Should that order ever turn around, the bounded run() around the startup
     * would win the race and leak Bunny's exception instead.
     *
     * On a connection of its own: this one is closed by the broker, and the test class shares
     * another with its own teardown.
     */
    public function testPrefetchRefusedByTheBrokerIsTranslated(): void
    {
        $queue = $this->givenEmptyQueue(
            new Exchange('test', new ExchangeType(ExchangeType::DIRECT)),
            'a_routing_key',
        );

        $connection = new BunnyConnection(Connection::fromDsn(new Dsn(self::dsn())));
        $consumer = new InMemoryConsumer(
            new AcknowledgeOperation($connection),
            new Configuration($queue->getName(), 1, 1),
        );

        // Only the catch around qos() raises this one, so getting it is the whole point: had the
        // race been won by the client error the same refusal ends up as Bunny's ClientException.
        self::expectException(ConfigurationFailed::class);
        self::expectExceptionMessage('Could not set prefetch-size/prefetch-count');

        (new ConsumerRunner($connection))->run($consumer);
    }

    /**
     * The time limit falling due while a message is being handled must not end the run there. A
     * handler that awaits anything suspends its own Fiber and turns the loop, which is where the
     * timer fires: ending the run then had run() return, the consumer cancelled and the connection
     * closed - by kernel.terminate or console.terminate - with the handler still parked, so the
     * acknowledge it went on to issue was lost and the message redelivered.
     */
    public function testStopWaitsForAMessageStillBeingHandled(): void
    {
        // One message only, so that what is left on the queue afterwards can only be this one.
        $queue = $this->givenQueueWithOneMessage();
        $consumer = new SuspendingConsumer(
            new AcknowledgeOperation($this->getConnection()),
            // A limit that falls due while the handler below still holds the message.
            new Configuration($queue->getName(), 1, 0, null, self::STOP_AFTER_SECONDS),
            self::SUSPEND_FOR_SECONDS,
        );

        $startedAt = microtime(true);
        $this->whenConsume($consumer);
        $consumingTook = microtime(true) - $startedAt;

        self::assertGreaterThanOrEqual(self::SUSPEND_FOR_SECONDS, $consumingTook);
        // The acknowledge reached the broker, so the message is gone rather than back on the queue.
        self::assertSame(0, $this->whenCountingMessagesLeft($queue));
    }

    /**
     * And the same handler failing still fails the run. Settling the promise when the timer fired
     * left this rejection landing on one that had settled long ago, where react/promise drops it -
     * the command exited successfully having half-processed a message.
     */
    public function testExceptionFromAMessageStillBeingHandledIsPropagated(): void
    {
        $queue = $this->givenQueueWithOneMessage();
        $consumer = new SuspendingConsumer(
            new AcknowledgeOperation($this->getConnection()),
            new Configuration($queue->getName(), 1, 0, null, self::STOP_AFTER_SECONDS),
            self::SUSPEND_FOR_SECONDS,
            true,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(SuspendingConsumer::EXCEPTION_MESSAGE);

        $this->whenConsume($consumer);
    }

    /**
     * A handler that publishes and then works on for a while - synchronous PHP, so it turns the
     * loop no more than the acknowledge after it does - had its message sit in React's write buffer
     * until it returned, and lose it altogether if the connection went away first.
     */
    public function testPublishFromAHandlerIsOnTheSocketBeforeTheHandlerReturns(): void
    {
        $this->givenEmptyQueues();

        $process = proc_open(
            [PHP_BINARY, __DIR__ . '/fixtures/publish-from-a-blocking-handler.php'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            [
                'RABBITMQ_DSN' => self::dsn(),
                'SOURCE_QUEUE_NAME' => self::HANDLER_SOURCE_QUEUE,
                'TARGET_QUEUE_NAME' => self::HANDLER_TARGET_QUEUE,
            ],
        );
        self::assertIsResource($process);

        $published = (string) fgets($pipes[1]);
        // Asked for while the handler is still blocked, so that only a flush from inside it can
        // answer - and before the kill, which a process cannot survive to flush anything either.
        $count = $this->whenCountingMessagesOn(self::HANDLER_TARGET_QUEUE);

        proc_terminate($process, self::SIGKILL);
        $errors = (string) stream_get_contents($pipes[2]);
        proc_close($process);
        $this->thenQueuesAreGone();

        self::assertStringContainsString('published', $published, $errors);
        self::assertSame(1, $count);
    }

    private static function dsn(): string
    {
        $dsn = getenv('RABBITMQ_DSN');

        assert(is_string($dsn));

        return $dsn;
    }

    private function whenCountingMessagesLeft(Queue $queue): int
    {
        $connection = $this->getConnection();

        // Declaring a queue that exists is how AMQP asks how many messages are ready on it.
        return $connection->run(
            static fn (): int => $connection->getChannel()->queueDeclare($queue->getName())->messageCount,
        );
    }

    private function givenEmptyQueues(): void
    {
        $connection = $this->getConnection();
        $connection->run(static function () use ($connection): void {
            foreach ([self::HANDLER_SOURCE_QUEUE, self::HANDLER_TARGET_QUEUE] as $queueName) {
                $connection->getChannel()->queueDeclare($queueName);
                $connection->getChannel()->queuePurge($queueName);
            }
        });
    }

    private function thenQueuesAreGone(): void
    {
        $connection = $this->getConnection();
        $connection->run(static function () use ($connection): void {
            foreach ([self::HANDLER_SOURCE_QUEUE, self::HANDLER_TARGET_QUEUE] as $queueName) {
                $connection->getChannel()->queueDelete($queueName);
            }
        });
    }

    private function whenCountingMessagesOn(string $queueName): int
    {
        $connection = $this->getConnection();
        $deadline = microtime(true) + self::BROKER_SECONDS;

        // More than once, because the broker counts a message when it has got round to it, not when
        // its bytes landed on the socket a moment earlier.
        do {
            $count = $connection->run(
                static fn (): int => $connection->getChannel()->queueDeclare($queueName)->messageCount,
            );

            if ($count > 0) {
                break;
            }

            usleep(self::POLL_MICROSECONDS);
        } while (microtime(true) < $deadline);

        return $count;
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
        $routingKey = 'a_routing_key';
        $queue = $this->givenEmptyQueue($exchange, $routingKey);

        $this->givenEnoughMessagesInQueue($exchange, $routingKey);

        return $queue;
    }

    private function givenQueueWithOneMessage(): Queue
    {
        $exchange = new Exchange('test', new ExchangeType(ExchangeType::DIRECT));
        $routingKey = 'a_routing_key';
        $queue = $this->givenEmptyQueue($exchange, $routingKey);

        $connection = $this->getConnection();
        $connection->run(static function () use ($connection, $exchange, $routingKey): void {
            $connection->getChannel()->publish('a message', [], $exchange->getName(), $routingKey);
        });

        return $queue;
    }

    private function givenEmptyQueue(Exchange $exchange, string $routingKey): Queue
    {
        $queue = new Queue('testQueue');
        $topology = new Topology(
            [$exchange],
            [],
            [$queue],
            [$queue->getName() => [new Binding($exchange, $routingKey)]],
        );
        $this->setupTopology($topology);

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
