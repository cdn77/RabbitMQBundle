<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\RabbitMQ;

use Cdn77\RabbitMQBundle\DependencyInjection\Configuration;

final class Queue implements Bindable
{
    /** @var string */
    private $name;

    /** @var bool */
    private $durable;

    /** @var bool */
    private $exclusive;

    /** @var Binding[] */
    private $bindings = [];

    /** @var bool */
    private $autoDelete;

    /** @var mixed[] */
    private $arguments;

    /**
     * @param mixed[] $arguments
     */
    public function __construct(
        string $name,
        bool $durable = false,
        bool $exclusive = false,
        bool $autoDelete = false,
        array $arguments = []
    ) {
        $this->name = $name;
        $this->durable = $durable;
        $this->exclusive = $exclusive;
        $this->autoDelete = $autoDelete;
        $this->arguments = $arguments;
    }

    /**
     * @param mixed[] $configuration
     */
    public static function fromConfiguration(string $name, array $configuration) : self
    {
        return new self(
            $name,
            $configuration[Configuration::KEY_QUEUE_DURABLE] ?? false,
            $configuration[Configuration::KEY_QUEUE_EXCLUSIVE] ?? false,
            $configuration[Configuration::KEY_QUEUE_AUTO_DELETE] ?? false,
            $configuration[Configuration::KEY_QUEUE_ARGUMENTS] ?? []
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

    /**
     * @return Binding[]
     */
    public function getBindings() : array
    {
        return $this->bindings;
    }

    public function isExclusive() : bool
    {
        return $this->exclusive;
    }

    public function shouldAutoDelete() : bool
    {
        return $this->autoDelete;
    }

    /**
     * @return mixed[]
     */
    public function getArguments() : array
    {
        return $this->arguments;
    }
}
