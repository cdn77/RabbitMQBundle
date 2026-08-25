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

    /**
     * A bounded run() per declaration rather than one around the lot. operation_timeout is what a
     * single round trip may take, and a topology of hundreds of items would otherwise run out of it
     * while every one of them was answered promptly.
     *
     * It also puts the timeout where it can be reported: run() raises it from outside the
     * operation's own Fiber, which stays suspended in the frame it is waiting for, so a catch
     * inside the closure never sees it. Wrapped around the whole topology, a single declaration
     * that hung left setup() throwing OperationFailed with nothing to say about which item it was.
     */
    public function setup(Topology $topology): void
    {
        $this->declareExchanges($topology);
        $this->declareQueues($topology);
    }

    private function declareExchanges(Topology $topology): void
    {
        foreach ($topology->getExchanges() as $exchange) {
            try {
                $this->connection->run(function () use ($exchange): void {
                    $this->connection->getChannel()->exchangeDeclare(
                        $exchange->getName(),
                        $exchange->getExchangeType()->getValue(),
                        false,
                        $exchange->isDurable(),
                        $exchange->shouldAutoDelete(),
                        $exchange->isInternal(),
                        false,
                        $exchange->getArguments(),
                    );
                });
            } catch (Throwable $exception) {
                throw ConfigurationFailed::cannotDeclareExchange($exchange, $exception);
            }

            foreach ($exchange->getBindings() as $binding) {
                try {
                    $this->connection->run(function () use ($exchange, $binding): void {
                        $this->connection->getChannel()->exchangeBind(
                            $exchange->getName(),
                            $binding->getBindable()->getName(),
                            $binding->getRoutingKey(),
                            false,
                            $binding->getArguments(),
                        );
                    });
                } catch (Throwable $exception) {
                    throw ConfigurationFailed::cannotBindExchange($exchange, $binding, $exception);
                }
            }
        }
    }

    private function declareQueues(Topology $topology): void
    {
        foreach ($topology->getQueues() as $queue) {
            try {
                $this->connection->run(function () use ($queue): void {
                    $this->connection->getChannel()->queueDeclare(
                        $queue->getName(),
                        false,
                        $queue->isDurable(),
                        $queue->isExclusive(),
                        $queue->shouldAutoDelete(),
                        false,
                        $queue->getArguments(),
                    );
                });
            } catch (Throwable $exception) {
                throw ConfigurationFailed::cannotDeclareQueue($queue, $exception);
            }

            foreach ($queue->getBindings() as $binding) {
                try {
                    $this->connection->run(function () use ($queue, $binding): void {
                        // Bunny 0.6 changed queue.bind argument order to (exchange, queue).
                        $this->connection->getChannel()->queueBind(
                            $binding->getBindable()->getName(),
                            $queue->getName(),
                            $binding->getRoutingKey(),
                            false,
                            $binding->getArguments(),
                        );
                    });
                } catch (Throwable $exception) {
                    throw ConfigurationFailed::cannotBindQueue($queue, $binding, $exception);
                }
            }
        }
    }
}
