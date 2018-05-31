<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class ConsumerCompilerPass implements CompilerPassInterface
{
    public const TAG_NAME_CONSUMER = RabbitMQExtension::ALIAS . '.consumer';

    public function process(ContainerBuilder $containerBuilder) : void
    {
        $consumerServices = [];

        $definition = $containerBuilder->setDefinition(
            ConsumerStorage::class,
            new Definition(
                ConsumerStorage::class,
                [$consumerServices]
            )
        );

        foreach ($containerBuilder->findTaggedServiceIds(self::TAG_NAME_CONSUMER) as $id => $tags) {
            $definition->addMethodCall('addConsumer', [new Reference($id)]);
        }
    }
}
