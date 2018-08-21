<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\Tests\Configuration;

use Cdn77\RabbitMQBundle\Configuration\Dsn;
use Cdn77\RabbitMQBundle\Exception\InvalidDsn;
use PHPUnit\Framework\TestCase;

class DsnTest extends TestCase
{
    public function testMalformedUri() : void
    {
        $this->expectException(InvalidDsn::class);
        $this->expectExceptionMessage('The provided DSN is malformed.');

        new Dsn('http:///example.com');
    }

    public function testMissingComponents() : void
    {
        $this->expectException(InvalidDsn::class);
        $this->expectExceptionMessage('The provided DSN is incomplete.');

        new Dsn('Fluff lobster ultimately, then mix with sweet chili sauce and serve roughly in sauté pan.');
    }

    public function testInvalidScheme() : void
    {
        $this->expectException(InvalidDsn::class);
        $this->expectExceptionMessage('The provided scheme "http" is invalid, expected "amqp".');

        new Dsn('http://example.com');
    }

    /**
     * @dataProvider dataProviderCreate
     * @param string[] $parameters
     */
    public function testCreate(
        string $amqpUri,
        ?string $username,
        ?string $password,
        string $host,
        int $port,
        string $vhost,
        array $parameters
    ) : void {
        $dsn = new Dsn($amqpUri);

        self::assertSame($username, $dsn->getUsername());
        self::assertSame($password, $dsn->getPassword());
        self::assertSame($host, $dsn->getHost());
        self::assertSame($port, $dsn->getPort());
        self::assertSame($vhost, $dsn->getVhost());
        self::assertSame($parameters, $dsn->getParameters());
    }

    /**
     * @return mixed[][]
     */
    public function dataProviderCreate() : iterable
    {
        yield [
            'amqp://username:password@host:1234/vhost?heartbeat=120',
            'username',
            'password',
            'host',
            1234,
            'vhost',
            ['heartbeat' => '120'],
        ];
        yield [
            'amqp://user@host/vhost',
            'user',
            null,
            'host',
            5672,
            'vhost',
            [],
        ];
        yield [
            'amqp://host/vhost',
            null,
            null,
            'host',
            5672,
            'vhost',
            [],
        ];
        yield [
            'amqp://host//vhost',
            null,
            null,
            'host',
            5672,
            '/vhost',
            [],
        ];
    }
}
