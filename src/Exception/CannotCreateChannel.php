<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\Exception;

use RuntimeException;

use function sprintf;

final class CannotCreateChannel extends RuntimeException implements Exception
{
    public static function gotInvalidType(string $expected, string $actual) : self
    {
        return new self(sprintf('Expected "%s", got "%s"', $expected, $actual));
    }
}
