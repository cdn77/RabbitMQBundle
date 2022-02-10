<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\DependencyInjection;

use Cdn77\RabbitMQBundle\RabbitMQ\Consumer\Consumer;
use InvalidArgumentException;

final class ConsumerStorage
{
    /** @var Consumer[] */
    private $consumers = [];

    /** @return Consumer[] */
    public function getConsumers(): array
    {
        return $this->consumers;
    }

    public function addConsumer(Consumer $consumer): void
    {
        if (isset($this->consumers[$consumer->getName()])) {
            throw new InvalidArgumentException('Multiple consumers with name: ' . $consumer->getName());
        }

        $this->consumers[$consumer->getName()] = $consumer;
    }
}
