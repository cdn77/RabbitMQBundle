<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\EventListener;

use Cdn77\RabbitMQBundle\RabbitMQ\Connection;
use React\EventLoop\Loop;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Closes the RabbitMQ connection once a request/command has finished.
 *
 * While connected, Bunny keeps a heartbeat timer on the ReactPHP event loop. That timer would keep
 * the loop — and therefore the php-fpm worker or console process — alive after the work is done.
 * Disconnecting on `kernel.terminate` (after the HTTP response is flushed) and on
 * `console.terminate` (after a command returns) drains the loop without delaying the response and
 * covers producers in both HTTP and CLI contexts. It is a no-op when nothing was ever published.
 */
final class DisconnectConnection implements EventSubscriberInterface
{
    /** @var Connection */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /** @return array<string, string> */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => 'disconnect',
            ConsoleEvents::TERMINATE => 'disconnect',
        ];
    }

    public function disconnect(object $event): void
    {
        if ($this->isInterruptedBySignal($event)) {
            // Closing the connection properly is out of the question here, so stop the loop
            // instead. Bunny's socket and heartbeat stay registered on it - what matters is that
            // both of the ways they could still keep the process alive are shut: the `run()` that
            // `React\Async` resumes from its own shutdown function returns, and `Loop::$stopped`,
            // which this sets, is what keeps React's shutdown autorun from starting the loop
            // again. Without it the process never reaches the end of its own exit(). The broker
            // requeues whatever stayed unacknowledged once the socket goes with the process.
            Loop::stop();

            return;
        }

        $this->connection->disconnect();

        if (! $event instanceof ConsoleTerminateEvent) {
            return;
        }

        // Not the belt and braces it looks like: a handshake that stalls leaves Bunny's socket on
        // the loop for good - `Client::connect()` neither closes the connection nor rolls the state
        // back when it throws, so `disconnect()` is left with a client it cannot disconnect - and
        // React's shutdown then blocks in `stream_select()` with no timeout. A command that could
        // not reach the broker would never exit. Both ways round in
        // `DisconnectConnectionTest::testCommandExitsAfterAHandshakeThatStalled`.
        //
        // It costs what it says: process-wide and permanent, so a later `console.terminate` listener
        // that puts work on the loop without awaiting it loses that work. Awaited work is fine -
        // `await()` enters the loop itself - and a process that hangs is worse than either. Console
        // only, all the same: a php-fpm worker serves further requests after `kernel.terminate`, and
        // their first await() would resume a scheduler Fiber sitting in a loop that no longer runs.
        Loop::stop();
    }

    /**
     * Symfony dispatches `console.terminate` from inside its `SIGINT`/`SIGTERM` handler as well, and
     * PHP forbids switching Fibers in a signal handler - broker I/O from there aborts with a
     * FiberError no matter which Fiber, if any, the signal happened to land on. So ask the event:
     * being on a Fiber or not says nothing about it, and a normal termination - `kernel.terminate`
     * included, which never carries a signal - has to disconnect either way.
     */
    private function isInterruptedBySignal(object $event): bool
    {
        return $event instanceof ConsoleTerminateEvent && $event->getInterruptingSignal() !== null;
    }
}
