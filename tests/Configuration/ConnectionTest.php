<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\Tests\Configuration;

use Cdn77\RabbitMQBundle\Configuration\Connection;
use Cdn77\RabbitMQBundle\Configuration\Dsn;
use Cdn77\RabbitMQBundle\DependencyInjection\Configuration;
use Cdn77\RabbitMQBundle\Exception\ConfigurationFailed;
use PHPUnit\Framework\TestCase;

/**
 * Bunny 0.6 arms a heartbeat timer whatever the interval is, so 0 - the AMQP way of switching
 * heartbeats off - is a timer due again as soon as it fires, spinning the event loop at a full core
 * for as long as any operation is awaiting. Both configuration paths have to say so instead.
 */
final class ConnectionTest extends TestCase
{
    private const string DSN = 'amqp://127.0.0.1/';
    private const string EXPECTED_MESSAGE = 'Heartbeat must be a positive number of seconds, 0 given';

    public function testHeartbeatSwitchedOffInTheDsnIsRejected(): void
    {
        self::expectException(ConfigurationFailed::class);
        self::expectExceptionMessage(self::EXPECTED_MESSAGE);

        Connection::fromDsn(new Dsn(self::DSN . '?heartbeat=0'));
    }

    public function testHeartbeatSwitchedOffInTheContainerConfigurationIsRejected(): void
    {
        self::expectException(ConfigurationFailed::class);
        self::expectExceptionMessage(self::EXPECTED_MESSAGE);

        Connection::fromDI([
            Configuration::KEY_CONFIGURATION_DSN => self::DSN,
            Configuration::KEY_CONFIGURATION_HEARTBEAT => 0,
        ]);
    }
}
