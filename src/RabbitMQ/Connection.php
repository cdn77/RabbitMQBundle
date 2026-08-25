<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\RabbitMQ;

use Bunny\ChannelInterface;
use Closure;

interface Connection
{
    public function getChannel(): ChannelInterface;

    public function getTransactionalChannel(): ChannelInterface;

    public function connect(): void;

    public function disconnect(): void;

    /**
     * Runs the given operation inside a Fiber so that the underlying Bunny
     * client/channel calls (which suspend on the event loop) can execute.
     *
     * Implementations must give the operation a Fiber of its own even when one is already current,
     * as it is for an acknowledge issued from inside a consumer callback: running it on the calling
     * Fiber saves an allocation but leaves PHP unable to switch back out of contexts that forbid it
     * - a signal handler above all - turning a survivable shutdown into a fatal FiberError. Nesting
     * run() calls is therefore allowed, and each nested call gets its own Fiber.
     *
     * The operation has to be bounded: nothing in Bunny gives up on a broker that stops answering,
     * so an implementation that waits forever hands the caller a process that can never finish.
     *
     * @param Closure(): T $operation
     *
     * @return T the value returned by $operation
     *
     * @template T
     */
    public function run(Closure $operation): mixed;

    /**
     * Same as run(), for the one operation that is meant to block: the consume loop, which returns
     * when the consumer's own message or time limit says so. Everything else wants run().
     *
     * @param Closure(): T $operation
     *
     * @return T the value returned by $operation
     *
     * @template T
     */
    public function runWithoutTimeout(Closure $operation): mixed;
}
