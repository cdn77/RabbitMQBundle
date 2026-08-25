<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\Tests;

use Bunny\Channel;
use Bunny\Exception\ChannelException;
use Bunny\Exception\ClientException;
use Bunny\Protocol\MethodBasicConsumeOkFrame;
use Bunny\Protocol\MethodBasicQosOkFrame;
use Cdn77\RabbitMQBundle\ConsumerRunner;
use Cdn77\RabbitMQBundle\Exception\ConfigurationFailed;
use Cdn77\RabbitMQBundle\Exception\OperationFailed;
use Cdn77\RabbitMQBundle\RabbitMQ\Connection;
use Cdn77\RabbitMQBundle\RabbitMQ\Consumer\Configuration;
use Cdn77\RabbitMQBundle\RabbitMQ\Operation\AcknowledgeOperation;
use Cdn77\RabbitMQBundle\Tests\RabbitMQ\InMemoryConsumer;
use Closure;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use React\EventLoop\Loop;

use function React\Async\async;
use function React\Async\await;

/**
 * How ConsumerRunner reports what the broker refuses - with a channel of its own rather than a real
 * broker, because a channel.close taken while awaiting leaves the connection unusable: bunny
 * 0.6.0-alpha.4 breaks React's await() scheduler for whoever took one, so an integration test
 * could not even clean up after itself. Partial mocks of Bunny's Channel keep its real event
 * emitter, which is what the ordering below hangs on.
 *
 * In their own processes: these emit into the shared event loop from a future tick, and an
 * operation that ends in a rejection can leave that tick unconsumed - it would then fire inside
 * some later test's await() and fail it with an exception from here.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
// A partial mock rather than a stub on purpose: stubbing every method would take Bunny's event
// emitter with it, and the emitting is the point. Nothing here asserts a call.
#[AllowMockObjectsWithoutExpectations]
final class ConsumerRunnerFailureTest extends TestCase
{
    private const string CHANNEL_CLOSED = 'Channel closed by server: NOT_FOUND - no queue \'aQueue\'';
    private const float OPERATION_TIMEOUT = 0.2;

    public function testQosFailureIsTranslated(): void
    {
        $channel = $this->createPartialMock(Channel::class, ['qos']);
        $channel->method('qos')->willThrowException(new ClientException('NOT_IMPLEMENTED'));

        $connection = $this->givenConnectionTo($channel);

        // A non-zero prefetch-size, the rejection RabbitMQ is guaranteed to answer with - though
        // only as far as the values go: a channel that throws where the real one is resumed with
        // the refusal by Bunny's await list. That path is covered live by
        // ConsumerRunnerTest::testPrefetchRefusedByTheBrokerIsTranslated. Nothing is ever consumed,
        // so the acknowledge operation is only there to build the consumer.
        $consumer = new InMemoryConsumer(
            new AcknowledgeOperation($connection),
            new Configuration('aQueue', 1, 1),
        );

        self::expectException(ConfigurationFailed::class);
        self::expectExceptionMessage('Could not set prefetch-size/prefetch-count');

        (new ConsumerRunner($connection))->run($consumer);
    }

    public function testChannelErrorOutrunsTheCloseFallback(): void
    {
        $channel = $this->createPartialMock(Channel::class, ['qos', 'consume', 'cancel']);
        $channel->method('qos')->willReturn(new MethodBasicQosOkFrame());
        $channel->method('cancel')->willReturn(false);
        $channel->method('consume')->willReturnCallback(
            static function () use ($channel): MethodBasicConsumeOkFrame {
                // What Bunny does with a channel.close frame, in that order and within one tick -
                // and only once consume() is through, which is when the runner starts listening.
                Loop::futureTick(static function () use ($channel): void {
                    $channel->emit('close');
                    $channel->emit('error', [new ChannelException(self::CHANNEL_CLOSED, 404)]);
                });

                $consumeOk = new MethodBasicConsumeOkFrame();
                $consumeOk->consumerTag = 'aConsumerTag';

                return $consumeOk;
            },
        );

        $connection = $this->givenConnectionTo($channel);
        $consumer = new InMemoryConsumer(
            new AcknowledgeOperation($connection),
            new Configuration('aQueue'),
        );

        // Not ConnectionFailed::channelClosedWithoutAnError(), which says nothing about this one.
        self::expectException(ChannelException::class);
        self::expectExceptionMessage(self::CHANNEL_CLOSED);

        (new ConsumerRunner($connection))->run($consumer);
    }

    /**
     * Opening the channel, basic.qos and basic.consume each wait for the broker to answer, and
     * Bunny leaves such a wait pending for good when the socket dies - the handlers the runner
     * installs are no help, a Fiber stuck in the handshake never reaches the await() they reject.
     * So the startup goes through the bounded run(), and whatever that reports ends the run.
     */
    public function testStartupIsBoundedByTheOperationTimeout(): void
    {
        $channel = $this->createPartialMock(Channel::class, ['qos', 'consume', 'cancel']);
        $channel->method('qos')->willReturn(new MethodBasicQosOkFrame());
        $channel->method('cancel')->willReturn(false);
        $channel->method('consume')->willReturnCallback(
            static function (): MethodBasicConsumeOkFrame {
                $consumeOk = new MethodBasicConsumeOkFrame();
                $consumeOk->consumerTag = 'aConsumerTag';

                return $consumeOk;
            },
        );

        $connection = self::createStub(Connection::class);
        $connection->method('run')->willThrowException(OperationFailed::timedOut(self::OPERATION_TIMEOUT));
        $connection->method('runWithoutTimeout')->willReturnCallback(
            static fn (Closure $operation) => await(async($operation)()),
        );
        $connection->method('getChannel')->willReturn($channel);

        // A time limit shorter than the suite can afford to wait: a startup that went unbounded
        // would find a broker that answers everything and nothing to consume, and had to end
        // somewhere.
        $consumer = new InMemoryConsumer(
            new AcknowledgeOperation($connection),
            new Configuration('aQueue', 1, 0, null, self::OPERATION_TIMEOUT),
        );

        self::expectException(OperationFailed::class);
        self::expectExceptionMessage('did not finish within');

        (new ConsumerRunner($connection))->run($consumer);
    }

    private function givenConnectionTo(Channel $channel): Connection
    {
        $connection = self::createStub(Connection::class);
        // Like BunnyConnection: a Fiber of its own, so that the runner's own await() suspends that
        // Fiber rather than the main context - the one place React's scheduler Fiber is shared with
        // whatever else ran before in this process.
        $onRun = static fn (Closure $operation) => await(async($operation)());
        $connection->method('run')->willReturnCallback($onRun);
        $connection->method('runWithoutTimeout')->willReturnCallback($onRun);
        $connection->method('getChannel')->willReturn($channel);

        return $connection;
    }
}
