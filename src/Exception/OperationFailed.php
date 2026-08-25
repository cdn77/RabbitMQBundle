<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\Exception;

use RuntimeException;
use Throwable;

use function sprintf;

final class OperationFailed extends RuntimeException implements Exception
{
    public static function timedOut(float $seconds, Throwable|null $previous = null): self
    {
        return new self(
            // %s, not a fixed number of decimals: a bound of 0.04 rounds to "0.0 seconds", which
            // is the value that switches the bound off.
            sprintf('Operation did not finish within %s seconds', $seconds),
            0,
            $previous,
        );
    }
}
