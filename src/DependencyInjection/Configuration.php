<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public const KEY_BINDING_EXCHANGE = 'exchange';
    public const KEY_BINDING_ROUTING_KEY = 'routing_key';
    public const KEY_BINDING_ARGUMENTS = 'arguments';
    public const KEY_CONFIGURATION_DSN = 'dsn';
    public const KEY_CONFIGURATION_HEARTBEAT = 'heartbeat';
    public const KEY_CONFIGURATION_CONNECTION_TIMEOUT = 'connection_timeout';
    public const KEY_CONFIGURATION_READ_WRITE_TIMEOUT = 'read_write_timeout';
    public const KEY_CONFIGURATION_EXCHANGES = 'exchanges';
    public const KEY_CONFIGURATION_QUEUES = 'queues';
    public const KEY_EXCHANGE_NAME = 'name';
    public const KEY_EXCHANGE_TYPE = 'type';
    public const KEY_EXCHANGE_DURABLE = 'durable';
    public const KEY_EXCHANGE_AUTO_DELETE = 'auto_delete';
    public const KEY_EXCHANGE_INTERNAL = 'internal';
    public const KEY_EXCHANGE_ARGUMENTS = 'arguments';
    public const KEY_EXCHANGE_BINDINGS = 'bindings';
    public const KEY_QUEUE_NAME = 'name';
    public const KEY_QUEUE_DURABLE = 'durable';
    public const KEY_QUEUE_EXCLUSIVE = 'exclusive';
    public const KEY_QUEUE_AUTO_DELETE = 'auto_delete';
    public const KEY_QUEUE_ARGUMENTS = 'arguments';
    public const KEY_QUEUE_BINDINGS = 'bindings';
    private const DEFAULT_DSN = 'amqp://127.0.0.1/';
    private const DEFAULT_HEARTBEAT = 60;
    private const DEFAULT_TIMEOUT = 10;
    private const DEAFULT_READ_WRITE_TIMEOUT = 3;

    public function getConfigTreeBuilder() : TreeBuilder
    {
        $treeBuilder = new TreeBuilder(RabbitMQExtension::ALIAS);

        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        $this->configureConnection($rootNode);
        $this->configureExchanges($rootNode);
        $this->configureQueues($rootNode);

        return $treeBuilder;
    }

    private function configureConnection(ArrayNodeDefinition $rootNode) : void
    {
        $rootNode->children()
            ->scalarNode(self::KEY_CONFIGURATION_DSN)
            ->defaultValue(self::DEFAULT_DSN);

        $rootNode->children()
            ->scalarNode(self::KEY_CONFIGURATION_HEARTBEAT)
            ->defaultValue(self::DEFAULT_HEARTBEAT);

        $rootNode->children()
            ->scalarNode(self::KEY_CONFIGURATION_CONNECTION_TIMEOUT)
            ->defaultValue(self::DEFAULT_TIMEOUT);

        $rootNode->children()
            ->scalarNode(self::KEY_CONFIGURATION_READ_WRITE_TIMEOUT)
            ->defaultValue(self::DEAFULT_READ_WRITE_TIMEOUT);
    }

    private function configureExchanges(ArrayNodeDefinition $rootNode) : void
    {
        /** @var ArrayNodeDefinition $exchangesNode */
        $exchangesNode = $rootNode->children()
            ->arrayNode(self::KEY_CONFIGURATION_EXCHANGES)
            ->useAttributeAsKey(self::KEY_EXCHANGE_NAME)
            ->normalizeKeys(false)
            ->defaultValue([])
            ->prototype('array');

        $exchangesNode->children()
            ->scalarNode(self::KEY_EXCHANGE_TYPE);

        $exchangesNode->children()
            ->booleanNode(self::KEY_EXCHANGE_DURABLE)
            ->defaultValue(false);

        $exchangesNode->children()
            ->booleanNode(self::KEY_EXCHANGE_AUTO_DELETE)
            ->defaultValue(false);

        $exchangesNode->children()
            ->booleanNode(self::KEY_EXCHANGE_INTERNAL)
            ->defaultValue(false);

        $exchangesNode->children()
            ->arrayNode(self::KEY_EXCHANGE_ARGUMENTS)
            ->normalizeKeys(false)
            ->prototype('scalar')
            ->defaultValue([]);

        $this->configureExchangeBindings($exchangesNode);
    }

    private function configureQueues(ArrayNodeDefinition $rootNode) : void
    {
        /** @var ArrayNodeDefinition $queuesNode */
        $queuesNode = $rootNode->children()
            ->arrayNode(self::KEY_CONFIGURATION_QUEUES)
            ->useAttributeAsKey(self::KEY_QUEUE_NAME)
            ->normalizeKeys(false)
            ->defaultValue([])
            ->prototype('array');

        $queuesNode->children()
            ->booleanNode(self::KEY_QUEUE_DURABLE)
            ->defaultValue(false);

        $queuesNode->children()
            ->booleanNode(self::KEY_QUEUE_EXCLUSIVE)
            ->defaultValue(false);

        $queuesNode->children()
            ->booleanNode(self::KEY_QUEUE_AUTO_DELETE)
            ->defaultValue(false);

        $queuesNode->children()
            ->arrayNode(self::KEY_QUEUE_ARGUMENTS)
            ->normalizeKeys(false)
            ->prototype('scalar')
            ->defaultValue([]);

        $this->configureQueueBindings($queuesNode);
    }

    private function configureExchangeBindings(ArrayNodeDefinition $exchangesNode) : void
    {
        /** @var ArrayNodeDefinition $exchangesBindingsNode */
        $exchangesBindingsNode = $exchangesNode->children()
            ->arrayNode(self::KEY_EXCHANGE_BINDINGS)
            ->normalizeKeys(false)
            ->defaultValue([])
            ->prototype('array');

        $this->configureBindingNode($exchangesBindingsNode);
    }

    private function configureQueueBindings(ArrayNodeDefinition $queuesNode) : void
    {
        /** @var ArrayNodeDefinition $queueBindingsNode */
        $queueBindingsNode = $queuesNode->children()
            ->arrayNode(self::KEY_QUEUE_BINDINGS)
            ->normalizeKeys(false)
            ->defaultValue([])
            ->prototype('array');

        $this->configureBindingNode($queueBindingsNode);
    }

    private function configureBindingNode(ArrayNodeDefinition $bindingsNode) : void
    {
        $bindingsNode->children()
            ->scalarNode(self::KEY_BINDING_EXCHANGE)
            ->isRequired();

        $bindingsNode->children()
            ->scalarNode(self::KEY_BINDING_ROUTING_KEY)
            ->defaultValue('');

        $bindingsNode->children()
            ->arrayNode(self::KEY_BINDING_ARGUMENTS)
            ->normalizeKeys(false)
            ->prototype('scalar')
            ->defaultValue([]);
    }
}
