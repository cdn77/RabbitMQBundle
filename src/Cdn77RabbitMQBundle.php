<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle;

use Cdn77\RabbitMQBundle\DependencyInjection\ConsumerCompilerPass;
use Cdn77\RabbitMQBundle\DependencyInjection\RabbitMQExtension;
use Cdn77\RabbitMQBundle\RabbitMQ\Consumer\Consumer;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class Cdn77RabbitMQBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->registerForAutoconfiguration(Consumer::class)
            ->addTag(ConsumerCompilerPass::TAG_NAME_CONSUMER);
        $container->addCompilerPass(new ConsumerCompilerPass());
    }

    public function getContainerExtension(): RabbitMQExtension
    {
        if ($this->extension === null) {
            $this->extension = new RabbitMQExtension();
        }

        return $this->extension;
    }
}
