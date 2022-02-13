<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\Configuration;

use Cdn77\RabbitMQBundle\DependencyInjection\Configuration;
use Cdn77\RabbitMQBundle\RabbitMQ\Binding;
use Cdn77\RabbitMQBundle\RabbitMQ\Exchange;
use Cdn77\RabbitMQBundle\RabbitMQ\Queue;

final class Topology
{
    /** @var Exchange[] */
    private $exchanges;

    /** @var Queue[] */
    private $queues;

    /**
     * @param Exchange[] $exchanges
     * @param Binding[][] $exchangeBindings
     * @param Queue[] $queues
     * @param Binding[][] $queueBindings
     */
    public function __construct(array $exchanges, array $exchangeBindings, array $queues, array $queueBindings)
    {
        $this->exchanges = $exchanges;
        foreach ($this->exchanges as $exchange) {
            if (! isset($exchangeBindings[$exchange->getName()])) {
                continue;
            }

            foreach ($exchangeBindings[$exchange->getName()] as $exchangeBinding) {
                $exchange->addBinding($exchangeBinding);
            }
        }

        $this->queues = $queues;
        foreach ($this->queues as $queue) {
            if (! isset($queueBindings[$queue->getName()])) {
                continue;
            }

            foreach ($queueBindings[$queue->getName()] as $queueBinding) {
                $queue->addBinding($queueBinding);
            }
        }
    }

    /** @param mixed[] $configuration */
    public static function fromDI(array $configuration): self
    {
        $exchanges = [];
        $queues = [];

        /** @var mixed[] $exchangesConfiguration */
        $exchangesConfiguration = $configuration[Configuration::KEY_CONFIGURATION_EXCHANGES];
        foreach ($exchangesConfiguration as $exchangeName => $exchangeConfiguration) {
            $exchange = Exchange::fromConfiguration($exchangeName, $exchangeConfiguration);

            $exchanges[$exchange->getName()] = $exchange;
        }

        $exchangesBindings = [];
        foreach ($exchangesConfiguration as $exchangeName => $exchangeConfiguration) {
            /** @var mixed[]|null $bindingsConfigurations */
            $bindingsConfigurations = $exchangeConfiguration[Configuration::KEY_EXCHANGE_BINDINGS] ?? null;
            if ($bindingsConfigurations === null) {
                continue;
            }

            foreach ($bindingsConfigurations as $bindingsConfiguration) {
                $exchangesBindings[$exchangeName][] = Binding::fromConfiguration(
                    $exchanges[$bindingsConfiguration[Configuration::KEY_BINDING_EXCHANGE]],
                    $bindingsConfiguration
                );
            }
        }

        /** @var mixed[] $queuesConfiguration */
        $queuesConfiguration = $configuration[Configuration::KEY_CONFIGURATION_QUEUES];
        foreach ($queuesConfiguration as $queueName => $queueConfiguration) {
            $queue = Queue::fromConfiguration($queueName, $queueConfiguration);

            $queues[$queue->getName()] = $queue;
        }

        $queuesBindings = [];
        foreach ($queuesConfiguration as $queueName => $queueConfiguration) {
            /** @var mixed[]|null $bindingsConfigurations */
            $bindingsConfigurations = $queueConfiguration[Configuration::KEY_QUEUE_BINDINGS] ?? null;
            if ($bindingsConfigurations === null) {
                continue;
            }

            foreach ($bindingsConfigurations as $bindingsConfiguration) {
                $queuesBindings[$queueName][] = Binding::fromConfiguration(
                    $exchanges[$bindingsConfiguration[Configuration::KEY_BINDING_EXCHANGE]],
                    $bindingsConfiguration
                );
            }
        }

        return new self($exchanges, $exchangesBindings, $queues, $queuesBindings);
    }

    /** @return Exchange[] */
    public function getExchanges(): array
    {
        return $this->exchanges;
    }

    /** @return Queue[] */
    public function getQueues(): array
    {
        return $this->queues;
    }
}
