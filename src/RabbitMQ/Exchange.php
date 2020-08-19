<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\RabbitMQ;

use Cdn77\RabbitMQBundle\DependencyInjection\Configuration;

final class Exchange implements Bindable
{
    /** @var string */
    private $name;

    /** @var ExchangeType */
    private $exchangeType;

    /** @var bool */
    private $durable;

    /** @var bool */
    private $autoDelete;

    /** @var bool */
    private $internal;

    /** @var mixed[] */
    private $arguments;

    /** @var Binding[] */
    private $bindings = [];

    /**
     * @param mixed[] $arguments
     */
    public function __construct(
        string $name,
        ExchangeType $exchangeType,
        bool $durable = true,
        bool $autoDelete = false,
        bool $internal = false,
        array $arguments = []
    ) {
        $this->name = $name;
        $this->exchangeType = $exchangeType;
        $this->durable = $durable;
        $this->autoDelete = $autoDelete;
        $this->internal = $internal;
        $this->arguments = $arguments;
    }

    /**
     * @param mixed[] $configuration
     */
    public static function fromConfiguration(string $name, array $configuration) : self
    {
        return new self(
            $name,
            new ExchangeType($configuration[Configuration::KEY_EXCHANGE_TYPE] ?? ExchangeType::DIRECT),
            $configuration[Configuration::KEY_EXCHANGE_DURABLE] ?? false,
            $configuration[Configuration::KEY_EXCHANGE_AUTO_DELETE] ?? false,
            $configuration[Configuration::KEY_EXCHANGE_INTERNAL] ?? false,
            $configuration[Configuration::KEY_EXCHANGE_ARGUMENTS] ?? []
        );
    }

    public function addBinding(Binding $binding) : void
    {
        $this->bindings[] = $binding;
    }

    public function getName() : string
    {
        return $this->name;
    }

    public function isDurable() : bool
    {
        return $this->durable;
    }

    public function getExchangeType() : ExchangeType
    {
        return $this->exchangeType;
    }

    /**
     * @return Binding[]
     */
    public function getBindings() : array
    {
        return $this->bindings;
    }

    public function shouldAutoDelete() : bool
    {
        return $this->autoDelete;
    }

    public function isInternal() : bool
    {
        return $this->internal;
    }

    /**
     * @return mixed[]
     */
    public function getArguments() : array
    {
        return $this->arguments;
    }
}
