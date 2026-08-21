<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle;

use Cdn77\RabbitMQBundle\Configuration\Topology;
use Cdn77\RabbitMQBundle\Exception\ConfigurationFailed;
use Cdn77\RabbitMQBundle\RabbitMQ\Connection;
use Throwable;

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
        $this->connection->run(function () use ($topology): void {
            $this->declareTopology($topology);
        });
    }

    private function declareTopology(Topology $topology): void
    {
        $channel = $this->connection->getChannel();

        foreach ($topology->getExchanges() as $exchange) {
            try {
                $channel->exchangeDeclare(
                    $exchange->getName(),
                    $exchange->getExchangeType()->getValue(),
                    false,
                    $exchange->isDurable(),
                    $exchange->shouldAutoDelete(),
                    $exchange->isInternal(),
                    false,
                    $exchange->getArguments(),
                );
            } catch (Throwable $exception) {
                throw ConfigurationFailed::cannotDeclareExchange($exchange, $exception);
            }

            foreach ($exchange->getBindings() as $binding) {
                $boundQueue = $binding->getBindable();

                try {
                    $channel->exchangeBind(
                        $exchange->getName(),
                        $boundQueue->getName(),
                        $binding->getRoutingKey(),
                        false,
                        $binding->getArguments(),
                    );
                } catch (Throwable $exception) {
                    throw ConfigurationFailed::cannotBindExchange($exchange, $binding, $exception);
                }
            }
        }

        foreach ($topology->getQueues() as $queue) {
            try {
                $channel->queueDeclare(
                    $queue->getName(),
                    false,
                    $queue->isDurable(),
                    $queue->isExclusive(),
                    $queue->shouldAutoDelete(),
                    false,
                    $queue->getArguments(),
                );
            } catch (Throwable $exception) {
                throw ConfigurationFailed::cannotDeclareQueue($queue, $exception);
            }

            foreach ($queue->getBindings() as $binding) {
                $boundQueue = $binding->getBindable();

                try {
                    // Bunny 0.6 changed queue.bind argument order to (exchange, queue).
                    $channel->queueBind(
                        $boundQueue->getName(),
                        $queue->getName(),
                        $binding->getRoutingKey(),
                        false,
                        $binding->getArguments(),
                    );
                } catch (Throwable $exception) {
                    throw ConfigurationFailed::cannotBindQueue($queue, $binding, $exception);
                }
            }
        }
    }
}
