<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\Exception;

use function sprintf;

final class InvalidDsn extends \LogicException implements Exception
{
    public static function malformed() : self
    {
        throw new self('The provided DSN is malformed.');
    }

    public static function missingComponents() : self
    {
        throw new self('The provided DSN is incomplete.');
    }

    public static function invalidScheme(string $provided, string $expected) : self
    {
        throw new self(sprintf('The provided scheme "%s" is invalid, expected "%s".', $provided, $expected));
    }
}
