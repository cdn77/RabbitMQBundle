<?php

declare(strict_types=1);

/**
 * Publishes one message and then does nothing at all - no further operation, no disconnect, nothing
 * that turns the event loop. Spawned and then killed by PublishOperationTest, which asks the broker
 * whether the message got out on its own.
 */

use Cdn77\RabbitMQBundle\Configuration\Connection;
use Cdn77\RabbitMQBundle\Configuration\Dsn;
use Cdn77\RabbitMQBundle\RabbitMQ\BunnyConnection;
use Cdn77\RabbitMQBundle\RabbitMQ\Operation\PublishOperation;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$dsn = getenv('RABBITMQ_DSN');
assert(is_string($dsn));
$queueName = getenv('QUEUE_NAME');
assert(is_string($queueName));

$connection = new BunnyConnection(Connection::fromDsn(new Dsn($dsn)));

(new PublishOperation())->handleRaw($connection, 'a message', [], $queueName, '');

echo "published\n";

// Killed from the outside while sitting here: exiting would run React's shutdown, which turns the
// loop and would flush the write buffer whether the publish had managed it or not. Long enough that
// the test is always the one to end this, short enough to leave nothing behind if it is not.
sleep(30);
