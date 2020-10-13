<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\Exception;

use Cdn77\RabbitMQBundle\RabbitMQ\Binding;
use Cdn77\RabbitMQBundle\RabbitMQ\Exchange;
use Cdn77\RabbitMQBundle\RabbitMQ\Queue;
use RuntimeException;

use function sprintf;

final class ConfigurationFailed extends RuntimeException implements Exception
{
    public static function invalidPrefetchValues() : self
    {
        return new self('Could not set prefetch-size/prefetch-count');
    }

    public static function cannotDeclareExchange(Exchange $exchange) : self
    {
        return new self(sprintf('Could not declare exchange %s', $exchange->getName()));
    }

    public static function cannotDeclareQueue(Queue $queue) : self
    {
        return new self(sprintf('Could not declare queue %s', $queue->getName()));
    }

    public static function cannotBindExchange(Exchange $exchange, Binding $binding) : self
    {
        return new self(
            sprintf(
                'Could not bind exchange "%s" to "%s" with routing key "%s"',
                $exchange->getName(),
                $binding->getBindable()->getName(),
                $binding->getRoutingKey()
            )
        );
    }

    public static function cannotBindQueue(Queue $queue, Binding $binding) : self
    {
        return new self(
            sprintf(
                'Could not bind queue "%s" to "%s" with routing key "%s"',
                $queue->getName(),
                $binding->getBindable()->getName(),
                $binding->getRoutingKey()
            )
        );
    }
}
