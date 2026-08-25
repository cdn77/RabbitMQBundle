<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\Tests\RabbitMQ\Operation;

use Cdn77\RabbitMQBundle\Configuration\Connection;
use Cdn77\RabbitMQBundle\Configuration\Dsn;
use Cdn77\RabbitMQBundle\Exception\OperationFailed;
use Cdn77\RabbitMQBundle\RabbitMQ\BunnyConnection;
use Cdn77\RabbitMQBundle\RabbitMQ\Message;
use Cdn77\RabbitMQBundle\RabbitMQ\Operation\PublishOperation;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

use function assert;
use function dirname;
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

/**
 * In its own process: a channel.close taken while awaiting leaves bunny 0.6.0-alpha.4 unable to
 * serve that connection again, and the operation Fiber parked in the rollback below is never
 * resumed - neither is anything a later test in the same process would want to rely on.
 */
#[Group('Integration')]
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class PublishOperationTest extends TestCase
{
    private const string UNFLUSHED_QUEUE = 'publishOperationTestQueue';
    private const int SIGKILL = 9;
    private const float BROKER_SECONDS = 3.0;
    private const int POLL_MICROSECONDS = 50000;

    /**
     * A batch has to report its failure as OperationFailed however that failure arrives, and this
     * one does not arrive through the operation's own Fiber: the broker's channel.close resumes the
     * commit, the rollback attempt awaits and so hands the read loop that same frame, and the
     * channel error it raises there reaches run() while the Fiber is still parked in the rollback.
     * A wrapper inside the operation was therefore never reached, and Bunny's ChannelException
     * leaked out of handleAll().
     */
    public function testBatchTheBrokerRefusesIsReportedAsAnOperationFailure(): void
    {
        $connection = new BunnyConnection(Connection::fromDsn(new Dsn(self::dsn())));

        self::expectException(OperationFailed::class);
        self::expectExceptionMessage("Channel closed by server: NOT_FOUND - no exchange 'no-such-exchange'");

        (new PublishOperation())->handleAll(
            $connection,
            [Message::json('{"a":1}'), Message::json('{"a":2}')],
            'a_routing_key',
            'no-such-exchange',
        );
    }

    /**
     * A publish has to be on the socket by the time handle() returns, which takes a turn of the
     * event loop: publish() awaits no reply, so nothing suspends and React holds the bytes in its
     * write buffer until an iteration finds the socket writable. Bunny only awaits a drain of its
     * own once that buffer passes 64 KiB - a body of 65488 bytes with the smallest framing - so
     * without the turn a producer of ordinary messages sends nothing at all, and the broker
     * eventually hangs up on it for missing heartbeats it never had the chance to write.
     *
     * A subprocess that is killed rather than allowed to exit: nothing in this process may turn the
     * loop before the broker is asked, and an orderly exit would run React's shutdown and flush the
     * buffer whether the publish had managed it or not.
     */
    public function testPublishIsOnTheSocketWhenItReturns(): void
    {
        $this->givenEmptyQueue();

        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__, 2) . '/fixtures/publish-without-flushing.php'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            ['RABBITMQ_DSN' => self::dsn(), 'QUEUE_NAME' => self::UNFLUSHED_QUEUE],
        );
        self::assertIsResource($process);

        $published = (string) fgets($pipes[1]);
        proc_terminate($process, self::SIGKILL);
        $errors = (string) stream_get_contents($pipes[2]);
        proc_close($process);

        self::assertStringContainsString('published', $published, $errors);
        $this->thenTheMessageReachedTheBroker();
    }

    private function givenEmptyQueue(): void
    {
        $connection = new BunnyConnection(Connection::fromDsn(new Dsn(self::dsn())));
        $connection->run(static function () use ($connection): void {
            $channel = $connection->getChannel();
            $channel->queueDeclare(self::UNFLUSHED_QUEUE);
            $channel->queuePurge(self::UNFLUSHED_QUEUE);
        });
        $connection->disconnect();
    }

    private function thenTheMessageReachedTheBroker(): void
    {
        $connection = new BunnyConnection(Connection::fromDsn(new Dsn(self::dsn())));
        $deadline = microtime(true) + self::BROKER_SECONDS;

        // Declaring a queue that exists is how AMQP asks how many messages are ready on it - and
        // asked more than once, because the broker counts a message when it has got round to it,
        // not when its bytes landed on the socket a moment earlier.
        do {
            $count = $connection->run(
                static fn (): int => $connection->getChannel()->queueDeclare(self::UNFLUSHED_QUEUE)->messageCount,
            );

            if ($count > 0) {
                break;
            }

            usleep(self::POLL_MICROSECONDS);
        } while (microtime(true) < $deadline);

        $connection->run(static fn () => $connection->getChannel()->queueDelete(self::UNFLUSHED_QUEUE));
        $connection->disconnect();

        self::assertSame(1, $count);
    }

    private static function dsn(): string
    {
        $dsn = getenv('RABBITMQ_DSN');

        assert(is_string($dsn));

        return $dsn;
    }
}
