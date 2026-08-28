<?php

declare(strict_types=1);

/**
 * Consumes one message and publishes another from inside the handler, which then goes on working -
 * synchronous PHP, so nothing turns the event loop for as long as it runs. Spawned and then killed
 * by ConsumerRunnerTest, which asks the broker for that second message while this is still blocked.
 */

use Bunny\Message;
use Cdn77\RabbitMQBundle\Configuration\Connection;
use Cdn77\RabbitMQBundle\Configuration\Dsn;
use Cdn77\RabbitMQBundle\ConsumerRunner;
use Cdn77\RabbitMQBundle\RabbitMQ\BunnyConnection;
use Cdn77\RabbitMQBundle\RabbitMQ\Consumer\Configuration;
use Cdn77\RabbitMQBundle\RabbitMQ\Consumer\Consumer;
use Cdn77\RabbitMQBundle\RabbitMQ\Operation\PublishOperation;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$dsn = getenv('RABBITMQ_DSN');
assert(is_string($dsn));
$sourceQueueName = getenv('SOURCE_QUEUE_NAME');
assert(is_string($sourceQueueName));
$targetQueueName = getenv('TARGET_QUEUE_NAME');
assert(is_string($targetQueueName));

$connection = new BunnyConnection(Connection::fromDsn(new Dsn($dsn)));
$publishOperation = new PublishOperation();
$publishOperation->handleRaw($connection, 'a message to consume', [], $sourceQueueName, '');

$consumer = new class ($connection, $publishOperation, $sourceQueueName, $targetQueueName) implements Consumer {
    public function __construct(
        private BunnyConnection $connection,
        private PublishOperation $publishOperation,
        private string $sourceQueueName,
        private string $targetQueueName,
    ) {
    }

    public function consume(Message $message): void
    {
        $this->publishOperation->handleRaw($this->connection, 'a message', [], $this->targetQueueName, '');

        echo "published\n";

        // Killed from the outside while sitting here, so that the handler returning can never be
        // what put the message above on the socket.
        sleep(30);
    }

    public function getName(): string
    {
        return 'blockingHandler';
    }

    public function getConfiguration(): Configuration
    {
        return new Configuration($this->sourceQueueName);
    }
};

(new ConsumerRunner($connection))->run($consumer);
