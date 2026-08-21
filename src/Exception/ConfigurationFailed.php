<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\Exception;

use Cdn77\RabbitMQBundle\RabbitMQ\Binding;
use Cdn77\RabbitMQBundle\RabbitMQ\Exchange;
use Cdn77\RabbitMQBundle\RabbitMQ\Queue;
use RuntimeException;
use Throwable;

use function sprintf;

final class ConfigurationFailed extends RuntimeException implements Exception
{
    public static function invalidPrefetchValues(Throwable|null $previous = null): self
    {
        return new self('Could not set prefetch-size/prefetch-count', 0, $previous);
    }

    public static function cannotDeclareExchange(Exchange $exchange, Throwable|null $previous = null): self
    {
        return new self(sprintf('Could not declare exchange %s', $exchange->getName()), 0, $previous);
    }

    public static function cannotDeclareQueue(Queue $queue, Throwable|null $previous = null): self
    {
        return new self(sprintf('Could not declare queue %s', $queue->getName()), 0, $previous);
    }

    public static function cannotBindExchange(
        Exchange $exchange,
        Binding $binding,
        Throwable|null $previous = null,
    ): self {
        return new self(
            sprintf(
                'Could not bind exchange "%s" to "%s" with routing key "%s"',
                $exchange->getName(),
                $binding->getBindable()->getName(),
                $binding->getRoutingKey(),
            ),
            0,
            $previous,
        );
    }

    public static function cannotBindQueue(Queue $queue, Binding $binding, Throwable|null $previous = null): self
    {
        return new self(
            sprintf(
                'Could not bind queue "%s" to "%s" with routing key "%s"',
                $queue->getName(),
                $binding->getBindable()->getName(),
                $binding->getRoutingKey(),
            ),
            0,
            $previous,
        );
    }
}
