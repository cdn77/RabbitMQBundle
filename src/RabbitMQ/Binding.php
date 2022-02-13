<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\RabbitMQ;

use Cdn77\RabbitMQBundle\DependencyInjection\Configuration;

final class Binding
{
    /** @var Bindable */
    private $bindable;

    /** @var string */
    private $routingKey;

    /** @var mixed[] */
    private $arguments;

    /** @param mixed[] $arguments */
    public function __construct(Bindable $bindable, string $routingKey, array $arguments = [])
    {
        $this->bindable = $bindable;
        $this->routingKey = $routingKey;
        $this->arguments = $arguments;
    }

    /** @param mixed[] $configuration */
    public static function fromConfiguration(Bindable $bindable, array $configuration): self
    {
        return new self(
            $bindable,
            $configuration[Configuration::KEY_BINDING_ROUTING_KEY],
            $configuration[Configuration::KEY_BINDING_ARGUMENTS] ?? []
        );
    }

    public function getBindable(): Bindable
    {
        return $this->bindable;
    }

    public function getRoutingKey(): string
    {
        return $this->routingKey;
    }

    /** @return mixed[] */
    public function getArguments(): array
    {
        return $this->arguments;
    }
}
