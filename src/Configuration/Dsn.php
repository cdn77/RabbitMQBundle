<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\Configuration;

use Cdn77\RabbitMQBundle\Exception\InvalidDsn;
use function count;
use function http_build_query;
use function parse_str;
use function parse_url;
use function sprintf;
use function substr;

final class Dsn
{
    private const SCHEME = 'rabbitmq';
    private const DEFAULT_PORT = 5672;

    /** @var string */
    private $host;

    /** @var int */
    private $port;

    /** @var string */
    private $username;

    /** @var string */
    private $password;

    /** @var string */
    private $vhost;

    /** @var string[] */
    private $parameters;

    public function __construct(string $dsn)
    {
        $parts = parse_url($dsn);

        if ($parts === false) {
            throw InvalidDsn::malformed();
        }

        if (!isset($parts['scheme'], $parts['user'], $parts['pass'], $parts['host'], $parts['path'])) {
            throw InvalidDsn::missingComponents();
        }

        if ($parts['scheme'] !== self::SCHEME) {
            throw InvalidDsn::invalidScheme($parts['scheme'], self::SCHEME);
        }

        $this->host = $parts['host'];
        $this->port = (int) ($parts['port'] ?? self::DEFAULT_PORT);
        $this->username = $parts['user'];
        $this->password = $parts['pass'];
        $this->vhost = $parts['path'] === '/' ? '/' : substr($parts['path'], 1);

        if (!isset($parts['query'])) {
            $this->parameters = [];
            return;
        }

        parse_str($parts['query'], $this->parameters);
    }

    public function getHost() : string
    {
        return $this->host;
    }

    public function getPort() : int
    {
        return $this->port;
    }

    public function getUsername() : string
    {
        return $this->username;
    }

    public function getPassword() : string
    {
        return $this->password;
    }

    public function getVhost() : string
    {
        return $this->vhost;
    }

    /**
     * @return string[]
     */
    public function getParameters() : array
    {
        return $this->parameters;
    }

    public function __toString() : string
    {
        return sprintf(
            '%s://%s:%s@%s:%s/%s%s%s',
            self::SCHEME,
            $this->username,
            $this->password,
            $this->host,
            $this->port,
            $this->vhost === '/' ? '' : $this->vhost,
            count($this->parameters) !== 0 ? '?' : '',
            count($this->parameters) !== 0 ? http_build_query($this->parameters) : ''
        );
    }
}
