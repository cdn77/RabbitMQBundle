<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\Configuration;

use Cdn77\RabbitMQBundle\DependencyInjection\Configuration;

final class Connection
{
    /** @var string */
    private $host;

    /** @var int */
    private $port;

    /** @var string */
    private $vhost;

    /** @var string */
    private $user;

    /** @var string */
    private $password;

    /** @var int */
    private $heartbeat;

    /** @var int */
    private $connectionTimeout;

    /** @var int */
    private $readWriteTimeout;

    public function __construct(
        string $host,
        int $port,
        string $vhost,
        string $user,
        string $password,
        int $heartbeat,
        int $connectionTimeout,
        int $readWriteTimeout
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->vhost = $vhost;
        $this->user = $user;
        $this->password = $password;
        $this->heartbeat = $heartbeat;
        $this->connectionTimeout = $connectionTimeout;
        $this->readWriteTimeout = $readWriteTimeout;
    }

    /** @param mixed[] $configuration */
    public static function fromDI(array $configuration) : self
    {
        return new self(
            $configuration[Configuration::KEY_CONFIGURATION_HOST],
            (int) $configuration[Configuration::KEY_CONFIGURATION_PORT],
            $configuration[Configuration::KEY_CONFIGURATION_VHOST],
            $configuration[Configuration::KEY_CONFIGURATION_USER],
            $configuration[Configuration::KEY_CONFIGURATION_PASSWORD],
            $configuration[Configuration::KEY_CONFIGURATION_HEARTBEAT],
            $configuration[Configuration::KEY_CONFIGURATION_CONNECTION_TIMEOUT],
            $configuration[Configuration::KEY_CONFIGURATION_READ_WRITE_TIMEOUT]
        );
    }

    public function getHost() : string
    {
        return $this->host;
    }

    public function getPort() : int
    {
        return $this->port;
    }

    public function getVhost() : string
    {
        return $this->vhost;
    }

    public function getUser() : string
    {
        return $this->user;
    }

    public function getPassword() : string
    {
        return $this->password;
    }

    public function getHeartbeat() : int
    {
        return $this->heartbeat;
    }

    public function getConnectionTimeout() : int
    {
        return $this->connectionTimeout;
    }

    public function getReadWriteTimeout() : int
    {
        return $this->readWriteTimeout;
    }
}
