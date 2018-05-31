<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class RabbitMQExtension extends Extension
{
    public const ALIAS = 'rabbitmq';

    /** @param mixed[] $configuration */
    public function load(array $configuration, ContainerBuilder $container) : void
    {
        $container->setParameter(
            self::ALIAS,
            $this->processConfiguration(new Configuration(), $configuration)
        );

        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../Resources/config')
        );

        $loader->load('services.yaml');
    }

    public function getAlias() : string
    {
        return self::ALIAS;
    }
}
