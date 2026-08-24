<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\Exception;

use LogicException;

use function implode;
use function sprintf;

final class InvalidDsn extends LogicException implements Exception
{
    public static function malformed(): self
    {
        throw new self('The provided DSN is malformed.');
    }

    public static function missingComponents(): self
    {
        throw new self('The provided DSN is incomplete.');
    }

    public static function invalidScheme(string $provided, string $expected): self
    {
        throw new self(sprintf('The provided scheme "%s" is invalid, expected "%s".', $provided, $expected));
    }

    /** @param array<int|string> $keys */
    public static function nestedParameters(array $keys): self
    {
        throw new self(sprintf(
            'The DSN query parameters "%s" are nested, expected a single value for each.',
            implode('", "', $keys)
        ));
    }
}
