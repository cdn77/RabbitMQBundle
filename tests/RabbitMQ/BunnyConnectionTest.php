<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\Tests\RabbitMQ;

use Cdn77\RabbitMQBundle\Configuration\Connection as ConnectionConfiguration;
use Cdn77\RabbitMQBundle\Configuration\Dsn;
use Cdn77\RabbitMQBundle\RabbitMQ\BunnyConnection;
use Cdn77\RabbitMQBundle\Tests\WithRabbitMQ;
use Fiber;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function React\Async\delay;

final class BunnyConnectionTest extends TestCase
{
    use WithRabbitMQ;

    private const string QUEUE = 'freshChannelQueue';

    /** Generous: the close frame is one round trip away, and a slow runner should not flake. */
    private const float CLOSE_ARRIVAL = 1.0;

    /**
     * A channel the broker closed is unusable - Bunny throws ChannelException('Channel is closed')
     * for every later call on it - so a cached one has to go when it closes, or a single 404 publish
     * would poison the connection for the rest of the process.
     */
    #[Group('Integration')]
    public function testChannelClosedByTheBrokerIsReplaced(): void
    {
        $connection = $this->getConnection();

        $stale = $connection->run(static fn () => $connection->getChannel());

        // RabbitMQ answers a publish to an exchange that does not exist by closing the channel.
        $connection->run(
            static fn () => $connection->getChannel()->publish('body', [], 'noSuchExchange', 'aKey'),
        );

        // That channel.close arrives on its own, with nothing awaiting it, so give the loop a turn.
        $connection->run(static fn () => delay(self::CLOSE_ARRIVAL));

        $fresh = $connection->run(static fn () => $connection->getChannel());

        // A working channel, not just a different object: the broker answers on it.
        $declareOk = $connection->run(
            static fn () => $connection->getChannel()->queueDeclare(self::QUEUE),
        );
        $connection->run(static fn () => $connection->getChannel()->queueDelete(self::QUEUE));

        self::assertNotSame($stale, $fresh);
        self::assertSame(self::QUEUE, $declareOk->queue);
    }

    /**
     * The Fiber the operation gets is part of the contract: reusing the calling one - as an
     * acknowledge issued from inside a consumer callback would - leaves PHP unable to switch back
     * out of a context that forbids it, and a shutdown from a signal handler dies with a FiberError
     * instead of just skipping the disconnect. run() itself talks to nobody, so no broker needed.
     */
    public function testRunGivesEveryOperationAFiberOfItsOwn(): void
    {
        $connection = new BunnyConnection(
            ConnectionConfiguration::fromDsn(new Dsn('amqp://127.0.0.1/')),
        );

        $callingFiber = Fiber::getCurrent();
        $outer = null;
        $inner = null;

        $connection->run(static function () use ($connection, &$outer, &$inner): void {
            $outer = Fiber::getCurrent();

            $connection->run(static function () use (&$inner): void {
                $inner = Fiber::getCurrent();
            });
        });

        self::assertInstanceOf(Fiber::class, $outer);
        self::assertInstanceOf(Fiber::class, $inner);
        self::assertNotSame($callingFiber, $outer);
        self::assertNotSame($outer, $inner);
    }

    protected function tearDown(): void
    {
        $this->getConnection()->disconnect();
    }
}
