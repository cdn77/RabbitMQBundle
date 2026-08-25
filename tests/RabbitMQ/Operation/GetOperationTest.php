<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\Tests\RabbitMQ\Operation;

use Cdn77\RabbitMQBundle\Configuration\Connection;
use Cdn77\RabbitMQBundle\Configuration\Dsn;
use Cdn77\RabbitMQBundle\RabbitMQ\BunnyConnection;
use Cdn77\RabbitMQBundle\RabbitMQ\Operation\GetOperation;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function assert;
use function getenv;
use function is_string;
use function microtime;

#[Group('Integration')]
final class GetOperationTest extends TestCase
{
    private const string QUEUE = 'getOperationTestQueue';
    private const int MESSAGE_COUNT = 1000;
    private const float OPERATION_TIMEOUT = 0.1;

    /**
     * operation_timeout is what one round trip may take, and a read is as many round trips as it
     * asks for messages: a bound around the whole loop ends a read the broker answered promptly
     * throughout - and takes the connection with it. The elapsed-time assertion is what keeps this
     * honest: the read has to outlast the bound for the count above it to mean anything.
     */
    public function testReadIsBoundedPerGetRatherThanAsAWhole(): void
    {
        $this->givenQueueWithMessages();

        $reader = new BunnyConnection(Connection::fromDsn(
            new Dsn(self::dsn() . '&operation_timeout=' . self::OPERATION_TIMEOUT),
        ));

        $startedAt = microtime(true);
        $messages = (new GetOperation($reader))->handle(self::QUEUE, self::MESSAGE_COUNT);
        $readTook = microtime(true) - $startedAt;

        $reader->disconnect();

        self::assertCount(self::MESSAGE_COUNT, $messages);
        self::assertGreaterThan(self::OPERATION_TIMEOUT, $readTook);
    }

    protected function tearDown(): void
    {
        $connection = new BunnyConnection(Connection::fromDsn(new Dsn(self::dsn())));
        $connection->run(static function () use ($connection): void {
            $connection->getChannel()->queueDelete(self::QUEUE);
        });
        $connection->disconnect();

        parent::tearDown();
    }

    private function givenQueueWithMessages(): void
    {
        $connection = new BunnyConnection(Connection::fromDsn(new Dsn(self::dsn())));
        $connection->run(static function () use ($connection): void {
            $channel = $connection->getChannel();
            $channel->queueDeclare(self::QUEUE);
            $channel->queuePurge(self::QUEUE);

            for ($count = 0; $count < self::MESSAGE_COUNT; $count++) {
                $channel->publish((string) $count, [], '', self::QUEUE);
            }
        });
        $connection->disconnect();
    }

    private static function dsn(): string
    {
        $dsn = getenv('RABBITMQ_DSN');

        assert(is_string($dsn));

        return $dsn;
    }
}
