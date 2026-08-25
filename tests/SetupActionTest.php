<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\Tests;

use Cdn77\RabbitMQBundle\Configuration\Topology;
use Cdn77\RabbitMQBundle\Exception\ConfigurationFailed;
use Cdn77\RabbitMQBundle\Exception\OperationFailed;
use Cdn77\RabbitMQBundle\RabbitMQ\Connection;
use Cdn77\RabbitMQBundle\RabbitMQ\Exchange;
use Cdn77\RabbitMQBundle\RabbitMQ\ExchangeType;
use Cdn77\RabbitMQBundle\RabbitMQ\Queue;
use Cdn77\RabbitMQBundle\SetupAction;
use PHPUnit\Framework\TestCase;

/**
 * The operation timeout is raised by Connection::run() from outside the operation's own Fiber, so a
 * declaration that hangs can only be named by whoever called run() - which is why every declaration
 * gets a run() of its own rather than the topology sharing one.
 */
final class SetupActionTest extends TestCase
{
    private const string EXCHANGE = 'anExchange';
    private const string QUEUE = 'aQueue';

    public function testTimedOutExchangeDeclarationNamesTheExchange(): void
    {
        $connection = $this->givenConnectionTimingOut();
        $topology = new Topology(
            [new Exchange(self::EXCHANGE, new ExchangeType(ExchangeType::DIRECT))],
            [],
            [],
            [],
        );

        self::expectException(ConfigurationFailed::class);
        self::expectExceptionMessage('Could not declare exchange ' . self::EXCHANGE);

        (new SetupAction($connection))->setup($topology);
    }

    public function testTimedOutQueueDeclarationNamesTheQueue(): void
    {
        $connection = $this->givenConnectionTimingOut();
        $topology = new Topology([], [], [new Queue(self::QUEUE)], []);

        self::expectException(ConfigurationFailed::class);
        self::expectExceptionMessage('Could not declare queue ' . self::QUEUE);

        (new SetupAction($connection))->setup($topology);
    }

    private function givenConnectionTimingOut(): Connection
    {
        $connection = self::createStub(Connection::class);
        $connection->method('run')->willThrowException(OperationFailed::timedOut(30.0));

        return $connection;
    }
}
