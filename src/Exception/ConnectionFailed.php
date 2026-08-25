<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\Exception;

use RuntimeException;
use Throwable;

final class ConnectionFailed extends RuntimeException implements Exception
{
    public static function causedBy(Throwable $previous): self
    {
        return new self('Connection to RabbitMQ failed', 0, $previous);
    }

    /**
     * Said of a channel that closed without an error to explain it, which is not the broker
     * refusing something on it - that comes with a reply code and text - but the channel going with
     * the connection: lost, or torn down locally.
     */
    public static function channelClosedWithoutAnError(): self
    {
        return new self(
            'Channel was closed with no error reported, so the connection was lost or closed locally'
                . ' - a consumer handler that blocks the event loop for longer than the heartbeat is'
                . ' the usual cause',
        );
    }
}
