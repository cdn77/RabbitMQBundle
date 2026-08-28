<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\Configuration;

use Cdn77\RabbitMQBundle\DependencyInjection\Configuration;
use Cdn77\RabbitMQBundle\Exception\ConfigurationFailed;

use function is_numeric;

final class Connection
{
    private const int DEFAULT_HEARTBEAT = 60;
    private const int DEFAULT_CONNECTION_TIMEOUT = 3;
    private const float DEFAULT_OPERATION_TIMEOUT = 30.0;

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

    /** @var float */
    private $operationTimeout;

    public function __construct(
        string $host,
        int $port,
        string $vhost,
        string|null $user,
        string|null $password,
        int $heartbeat = self::DEFAULT_HEARTBEAT,
        int $connectionTimeout = self::DEFAULT_CONNECTION_TIMEOUT,
        float $operationTimeout = self::DEFAULT_OPERATION_TIMEOUT,
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->vhost = $vhost;
        $this->user = $user;
        $this->password = $password;
        $this->heartbeat = self::validHeartbeat($heartbeat);
        $this->connectionTimeout = $connectionTimeout;
        $this->operationTimeout = $operationTimeout;
    }

    /** @param mixed[] $configuration */
    public static function fromDI(array $configuration): self
    {
        $dsn = new Dsn($configuration[Configuration::KEY_CONFIGURATION_DSN]);
        $new = self::fromDsn($dsn);

        if (
            isset($configuration[Configuration::KEY_CONFIGURATION_HEARTBEAT])
            && ! isset($dsn->getParameters()[Configuration::KEY_CONFIGURATION_HEARTBEAT])
        ) {
            $new->heartbeat = self::validHeartbeat(
                (int) $configuration[Configuration::KEY_CONFIGURATION_HEARTBEAT],
            );
        }

        if (
            isset($configuration[Configuration::KEY_CONFIGURATION_CONNECTION_TIMEOUT])
            && ! isset($dsn->getParameters()[Configuration::KEY_CONFIGURATION_CONNECTION_TIMEOUT])
        ) {
            $new->connectionTimeout = (int) $configuration[Configuration::KEY_CONFIGURATION_CONNECTION_TIMEOUT];
        }

        // Only a number, never a blind cast: garbage would become 0.0, which is how the timeout
        // is switched off - the one value nobody means to configure by accident.
        $operationTimeout = $configuration[Configuration::KEY_CONFIGURATION_OPERATION_TIMEOUT] ?? null;
        if (
            is_numeric($operationTimeout)
            && ! isset($dsn->getParameters()[Configuration::KEY_CONFIGURATION_OPERATION_TIMEOUT])
        ) {
            $new->operationTimeout = (float) $operationTimeout;
        }

        return $new;
    }

    public static function fromDsn(Dsn $dsn): self
    {
        $parameters = $dsn->getParameters();
        $operationTimeout = $parameters[Configuration::KEY_CONFIGURATION_OPERATION_TIMEOUT] ?? null;

        return new self(
            $dsn->getHost(),
            $dsn->getPort(),
            $dsn->getVhost(),
            $dsn->getUsername(),
            $dsn->getPassword(),
            (int) ($parameters[Configuration::KEY_CONFIGURATION_HEARTBEAT] ?? self::DEFAULT_HEARTBEAT),
            (int) ($parameters[Configuration::KEY_CONFIGURATION_CONNECTION_TIMEOUT]
                ?? self::DEFAULT_CONNECTION_TIMEOUT),
            is_numeric($operationTimeout) ? (float) $operationTimeout : self::DEFAULT_OPERATION_TIMEOUT,
        );
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function getVhost(): string
    {
        return $this->vhost;
    }

    public function getUser(): string|null
    {
        return $this->user;
    }

    public function getPassword(): string|null
    {
        return $this->password;
    }

    public function getHeartbeat(): int
    {
        return $this->heartbeat;
    }

    public function getConnectionTimeout(): int
    {
        return $this->connectionTimeout;
    }

    /** How long a single broker operation may take before it is given up on. Zero disables it. */
    public function getOperationTimeout(): float
    {
        return $this->operationTimeout;
    }

    /**
     * Bunny 0.6.0-alpha.4 arms a heartbeat timer whatever the interval is and re-arms it with the
     * same value, so 0 - the AMQP way of switching heartbeats off - leaves a timer that is due
     * again the moment it fires. It then spins the event loop at a full core for the whole of every
     * operation and floods the broker with heartbeat frames: measured at 0.78s of CPU for a
     * one-second await, against 0.00s with an interval of 60. Rejected here rather than in
     * BunnyConnection, so that a DSN parameter, a container key and a hand-built configuration are
     * all covered - including the blind cast above, which turns any non-numeric value into a zero.
     */
    private static function validHeartbeat(int $heartbeat): int
    {
        if ($heartbeat > 0) {
            return $heartbeat;
        }

        throw ConfigurationFailed::heartbeatMustBePositive($heartbeat);
    }
}
