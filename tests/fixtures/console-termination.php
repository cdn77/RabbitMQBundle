<?php

declare(strict_types=1);

/**
 * A console command as the bundle sees one: it tries to reach the broker, fails, and has its
 * connection closed by the DisconnectConnection subscriber on console.terminate. Run as a
 * subprocess by DisconnectConnectionTest, which cares only about whether it manages to exit.
 */

use Cdn77\RabbitMQBundle\Configuration\Connection;
use Cdn77\RabbitMQBundle\Configuration\Dsn;
use Cdn77\RabbitMQBundle\EventListener\DisconnectConnection;
use Cdn77\RabbitMQBundle\RabbitMQ\BunnyConnection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\EventDispatcher\EventDispatcher;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$dsn = getenv('RABBITMQ_DSN');
assert(is_string($dsn));

$connection = new BunnyConnection(Connection::fromDsn(new Dsn($dsn)));

$dispatcher = new EventDispatcher();
$dispatcher->addSubscriber(new DisconnectConnection($connection));

try {
    $connection->connect();
} catch (Throwable $error) {
    echo 'connect failed: ', $error::class, "\n";
}

$dispatcher->dispatch(
    new ConsoleTerminateEvent(new Command('test'), new ArrayInput([]), new NullOutput(), 0, null),
    ConsoleEvents::TERMINATE,
);

echo "terminated\n";
