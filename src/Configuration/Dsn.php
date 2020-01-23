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
    private const SCHEME = 'amqp';
    private const DEFAULT_PORT = 5672;
    private const DEFAULT_VHOST = '/';

    /** @var string */
    private $host;

    /** @var int */
    private $port;

    /** @var string|null */
    private $username;

    /** @var string|null */
    private $password;

    /** @var string */
    private $vhost;

    /** @var string[] */
    private $parameters = [];

    public function __construct(string $dsn)
    {
        $parts = parse_url($dsn);

        if ($parts === false) {
            throw InvalidDsn::malformed();
        }

        if (! isset($parts['scheme'], $parts['host'])) {
            throw InvalidDsn::missingComponents();
        }

        if ($parts['scheme'] !== self::SCHEME) {
            throw InvalidDsn::invalidScheme($parts['scheme'], self::SCHEME);
        }

        $this->host = $parts['host'];
        $this->port = $parts['port'] ?? self::DEFAULT_PORT;
        $this->username = $parts['user'] ?? null;
        $this->password = $parts['pass'] ?? null;
        $this->vhost = $parts['path'] === '/' ? self::DEFAULT_VHOST : substr($parts['path'], 1);

        if (! isset($parts['query'])) {
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

    public function getUsername() : ?string
    {
        return $this->username;
    }

    public function getPassword() : ?string
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
