<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\Configuration;

use Cdn77\RabbitMQBundle\DependencyInjection\Configuration;

final class Connection
{
    private const DEFAULT_HEARTBEAT = 60;
    private const DEFAULT_CONNECTION_TIMEOUT = 3;
    private const DEFAULT_READ_WRITE_TIMEOUT = 5;

    /** @var string */
    private $host;

    /** @var int */
    private $port;

    /** @var string */
    private $vhost;

    /** @var string|null */
    private $user;

    /** @var string|null */
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
        ?string $user,
        ?string $password,
        int $heartbeat = self::DEFAULT_HEARTBEAT,
        int $connectionTimeout = self::DEFAULT_CONNECTION_TIMEOUT,
        int $readWriteTimeout = self::DEFAULT_READ_WRITE_TIMEOUT
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

    /**
     * @param mixed[] $configuration
     */
    public static function fromDI(array $configuration) : self
    {
        $dsn = new Dsn($configuration[Configuration::KEY_CONFIGURATION_DSN]);
        $new = self::fromDsn($dsn);

        if (isset($configuration[Configuration::KEY_CONFIGURATION_HEARTBEAT])
            && ! isset($dsn->getParameters()[Configuration::KEY_CONFIGURATION_HEARTBEAT])
        ) {
            $new->heartbeat = (int) $configuration[Configuration::KEY_CONFIGURATION_HEARTBEAT];
        }

        if (isset($configuration[Configuration::KEY_CONFIGURATION_CONNECTION_TIMEOUT])
            && ! isset($dsn->getParameters()[Configuration::KEY_CONFIGURATION_CONNECTION_TIMEOUT])
        ) {
            $new->connectionTimeout = (int) $configuration[Configuration::KEY_CONFIGURATION_CONNECTION_TIMEOUT];
        }

        if (isset($configuration[Configuration::KEY_CONFIGURATION_READ_WRITE_TIMEOUT])
            && ! isset($dsn->getParameters()[Configuration::KEY_CONFIGURATION_READ_WRITE_TIMEOUT])
        ) {
            $new->readWriteTimeout = (int) $configuration[Configuration::KEY_CONFIGURATION_READ_WRITE_TIMEOUT];
        }

        return $new;
    }

    public static function fromDsn(Dsn $dsn) : self
    {
        $parameters = $dsn->getParameters();

        return new self(
            $dsn->getHost(),
            $dsn->getPort(),
            $dsn->getVhost(),
            $dsn->getUsername(),
            $dsn->getPassword(),
            (int) ($parameters[Configuration::KEY_CONFIGURATION_HEARTBEAT] ?? self::DEFAULT_HEARTBEAT),
            (int) ($parameters[Configuration::KEY_CONFIGURATION_CONNECTION_TIMEOUT]
                ?? self::DEFAULT_CONNECTION_TIMEOUT),
            (int) ($parameters[Configuration::KEY_CONFIGURATION_READ_WRITE_TIMEOUT] ?? self::DEFAULT_READ_WRITE_TIMEOUT)
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

    public function getUser() : ?string
    {
        return $this->user;
    }

    public function getPassword() : ?string
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
