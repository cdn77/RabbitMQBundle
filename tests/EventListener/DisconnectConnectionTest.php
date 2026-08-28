<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\Tests\EventListener;

use Cdn77\RabbitMQBundle\EventListener\DisconnectConnection;
use Cdn77\RabbitMQBundle\RabbitMQ\Connection;
use Fiber;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\KernelEvents;

use function dirname;
use function fclose;
use function microtime;
use function proc_close;
use function proc_get_status;
use function proc_open;
use function proc_terminate;
use function stream_get_contents;
use function stream_socket_get_name;
use function stream_socket_server;
use function usleep;

use const PHP_BINARY;

/**
 * Every test here runs in its own process: terminating stops the event loop, which is process-wide
 * and outlives the test - React's shared scheduler Fiber would be left in a run() that returns the
 * moment anything resumes it, and the next await() in the suite would die with
 * `AssertionError: assert(\is_callable($ret))`.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class DisconnectConnectionTest extends TestCase
{
    /** SIGTERM, spelled out so that the test does not need the pcntl extension. */
    private const int SIGNAL = 15;
    private const float HANDSHAKE_TIMEOUT = 1.0;
    private const float EXIT_ALLOWANCE = 5.0;
    private const int POLL_MICROSECONDS = 20000;
    private const int SIGKILL = 9;

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

    /**
     * The Loop::stop() on a normal console termination is not the belt-and-braces it looks like: a
     * handshake that stalls leaves Bunny's socket on the event loop for good, since
     * Client::connect() neither closes the connection nor rolls the state back when it throws
     * (bunny 0.6.0-alpha.4), and disconnect() cannot take it off - the client never reached a state
     * it can be disconnected from. React's shutdown then blocks in stream_select() with nothing to
     * wake it and this subprocess runs until it is killed, which is what happens if that one line
     * is ever removed.
     *
     * A subprocess because there is no other way to tell a process that exits from one that hangs,
     * and because stopping the loop is process-wide.
     */
    public function testCommandExitsAfterAHandshakeThatStalled(): void
    {
        $listener = stream_socket_server('tcp://127.0.0.1:0', $errorNumber, $errorMessage);
        self::assertIsResource($listener, (string) $errorMessage);

        // Never accepted, so the connection completes from the backlog and the handshake then waits
        // for a greeting that is never coming - the operation timeout is what ends it.
        $address = stream_socket_get_name($listener, false);
        self::assertIsString($address);

        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__) . '/fixtures/console-termination.php'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            [
                'RABBITMQ_DSN' => 'amqp://' . $address
                    . '/?heartbeat=60&operation_timeout=' . self::HANDSHAKE_TIMEOUT,
            ],
        );
        self::assertIsResource($process);

        $deadline = microtime(true) + self::HANDSHAKE_TIMEOUT + self::EXIT_ALLOWANCE;
        while (proc_get_status($process)['running'] === true && microtime(true) < $deadline) {
            usleep(self::POLL_MICROSECONDS);
        }

        $running = proc_get_status($process)['running'] === true;

        // Kill it before reading: stream_get_contents() waits for the end of a pipe that a process
        // still holding it open is never going to give.
        if ($running) {
            proc_terminate($process, self::SIGKILL);
        }

        $output = (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);

        proc_close($process);
        fclose($listener);

        self::assertStringContainsString('connect failed', $output);
        self::assertFalse($running, 'The command was still running: ' . $output);
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
