<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\Tests;

use Cdn77\RabbitMQBundle\Configuration\Connection;
use Cdn77\RabbitMQBundle\Configuration\Topology;
use Cdn77\RabbitMQBundle\ConsumerRunner;
use Cdn77\RabbitMQBundle\RabbitMQ\BunnyConnection;
use Cdn77\RabbitMQBundle\SetupAction;
use function assert;
use function getenv;
use function is_string;

trait WithRabbitMQ
{
    /**
     * @internal
     * @var BunnyConnection
     */
    private $connection;

    /**
     * @internal
     * @var ConsumerRunner
     */
    private $consumerRunner;

    public function setupTopology(Topology $topologyConfiguration) : void
    {
        $setupAction = new SetupAction($this->connection);
        $setupAction->setup($topologyConfiguration);
    }

    public function getConnection() : BunnyConnection
    {
        return $this->connection;
    }

    public function getConsumerRunner() : ConsumerRunner
    {
        if ($this->consumerRunner === null) {
            $this->consumerRunner = new ConsumerRunner($this->getConnection());
        }

        return $this->consumerRunner;
    }

    /**
     * @internal
     * @before
     */
    protected function setupRabbitMQ() : void
    {
        $host = getenv('RABBITMQ_HOST');
        $port = getenv('RABBITMQ_PORT');
        $vhost = getenv('RABBITMQ_VHOST');
        $username = getenv('RABBITMQ_USERNAME');
        $password = getenv('RABBITMQ_PASSWORD');

        assert(is_string($host));
        assert(is_string($port));
        assert(is_string($vhost));
        assert(is_string($username));
        assert(is_string($password));

        $connectionConfiguration = new Connection(
            $host,
            (int) $port,
            $vhost,
            $username,
            $password,
            60,
            10,
            3
        );

        $this->connection = new BunnyConnection($connectionConfiguration);
    }
}
