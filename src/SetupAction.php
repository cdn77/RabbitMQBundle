<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle;

use Bunny\Protocol\MethodExchangeBindOkFrame;
use Bunny\Protocol\MethodExchangeDeclareOkFrame;
use Bunny\Protocol\MethodQueueBindOkFrame;
use Bunny\Protocol\MethodQueueDeclareOkFrame;
use Cdn77\RabbitMQBundle\Configuration\Topology;
use Cdn77\RabbitMQBundle\Exception\ConfigurationFailed;
use Cdn77\RabbitMQBundle\RabbitMQ\Connection;

final class SetupAction
{
    /** @var Connection */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function setup(Topology $topology): void
    {
        $channel = $this->connection->getChannel();

        foreach ($topology->getExchanges() as $exchange) {
            $frame = $channel->exchangeDeclare(
                $exchange->getName(),
                $exchange->getExchangeType()->getValue(),
                false,
                $exchange->isDurable(),
                $exchange->shouldAutoDelete(),
                $exchange->isInternal(),
                false,
                $exchange->getArguments()
            );

            if (! ($frame instanceof MethodExchangeDeclareOkFrame)) {
                throw ConfigurationFailed::cannotDeclareExchange($exchange);
            }

            foreach ($exchange->getBindings() as $binding) {
                $boundQueue = $binding->getBindable();

                $frame = $channel->exchangeBind(
                    $exchange->getName(),
                    $boundQueue->getName(),
                    $binding->getRoutingKey(),
                    false,
                    $binding->getArguments()
                );

                if (! ($frame instanceof MethodExchangeBindOkFrame)) {
                    throw ConfigurationFailed::cannotBindExchange($exchange, $binding);
                }
            }
        }

        foreach ($topology->getQueues() as $queue) {
            $frame = $channel->queueDeclare(
                $queue->getName(),
                false,
                $queue->isDurable(),
                $queue->isExclusive(),
                $queue->shouldAutoDelete(),
                false,
                $queue->getArguments()
            );

            if (! ($frame instanceof MethodQueueDeclareOkFrame)) {
                throw ConfigurationFailed::cannotDeclareQueue($queue);
            }

            foreach ($queue->getBindings() as $binding) {
                $boundQueue = $binding->getBindable();
                $frame = $channel->queueBind(
                    $queue->getName(),
                    $boundQueue->getName(),
                    $binding->getRoutingKey(),
                    false,
                    $binding->getArguments()
                );

                if (! ($frame instanceof MethodQueueBindOkFrame)) {
                    throw ConfigurationFailed::cannotBindQueue($queue, $binding);
                }
            }
        }
    }
}
