<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\Exception;

use InvalidArgumentException;

use function sprintf;

final class ConsumerFailed extends InvalidArgumentException implements Exception
{
    public static function doesNotExist(string $consumerName): self
    {
        return new self(sprintf('Consumer "%s" does not exist', $consumerName));
    }
}
