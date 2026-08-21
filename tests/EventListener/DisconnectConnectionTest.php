<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\Tests\EventListener;

use Cdn77\RabbitMQBundle\EventListener\DisconnectConnection;
use Cdn77\RabbitMQBundle\RabbitMQ\Connection;
use Fiber;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\KernelEvents;

final class DisconnectConnectionTest extends TestCase
{
    /** SIGTERM, spelled out so that the test does not need the pcntl extension. */
    private const int SIGNAL = 15;

    public function testDisconnectsOnHttpAndConsoleTermination(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::exactly(2))->method('disconnect');

        $dispatcher = $this->givenDispatcher($connection);

        // Only the absence of an interrupting signal on the event matters here, and the real
        // TerminateEvent is final and would drag symfony/http-foundation in just to be built.
        $dispatcher->dispatch(new stdClass(), KernelEvents::TERMINATE);
        $dispatcher->dispatch($this->consoleTermination(null), ConsoleEvents::TERMINATE);
    }

    /**
     * In its own process: the subscriber stops the event loop, which is process-wide and outlives
     * the test - React's shared scheduler Fiber is left in a run() that returns the moment anything
     * resumes it, so the next await() in the suite would die with
     * `AssertionError: assert(\is_callable($ret))`.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testDoesNotDisconnectWhenTerminatingFromSignalHandler(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::never())->method('disconnect');

        $dispatcher = $this->givenDispatcher($connection);

        $dispatcher->dispatch($this->consoleTermination(self::SIGNAL), ConsoleEvents::TERMINATE);
    }

    public function testDisconnectsFromInsideFiber(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('disconnect');

        $dispatcher = $this->givenDispatcher($connection);

        // Being on a Fiber says nothing about being in a signal handler: a command that ended up
        // dispatching its termination from one still has to have its heartbeat timer taken off the
        // event loop, or the process would never exit.
        $event = $this->consoleTermination(null);
        $fiber = new Fiber(static function () use ($dispatcher, $event): void {
            $dispatcher->dispatch($event, ConsoleEvents::TERMINATE);
        });
        $fiber->start();

        self::assertTrue($fiber->isTerminated());
    }

    private function givenDispatcher(Connection $connection): EventDispatcher
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new DisconnectConnection($connection));

        return $dispatcher;
    }

    private function consoleTermination(int|null $interruptingSignal): ConsoleTerminateEvent
    {
        return new ConsoleTerminateEvent(
            new Command('test'),
            new ArrayInput([]),
            new NullOutput(),
            0,
            $interruptingSignal,
        );
    }
}
