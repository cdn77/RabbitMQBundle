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
}
