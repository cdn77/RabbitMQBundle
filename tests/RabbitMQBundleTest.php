<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\Tests;

use Cdn77\RabbitMQBundle\Cdn77RabbitMQBundle;
use Cdn77\RabbitMQBundle\DependencyInjection\ConsumerCompilerPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class RabbitMQBundleTest extends TestCase
{
    /** @var Cdn77RabbitMQBundle */
    private $bundle;

    public function testBuild() : void
    {
        $containerBuilder = new ContainerBuilder();
        $this->bundle->build($containerBuilder);
        $passConfig = $containerBuilder->getCompiler()->getPassConfig();
        $beforeOptimizationPasses = $passConfig->getBeforeOptimizationPasses();
        $containsConsumerCompilerPass = false;
        foreach ($beforeOptimizationPasses as $compilerPass) {
            if (! ($compilerPass instanceof ConsumerCompilerPass)) {
                continue;
            }

            $containsConsumerCompilerPass = true;
        }

        self::assertTrue(
            $containsConsumerCompilerPass,
            'RabbitMQ bundle does not have registered consumer compiler pass.'
        );
    }

    protected function setUp() : void
    {
        $this->bundle = new Cdn77RabbitMQBundle();
    }
}
