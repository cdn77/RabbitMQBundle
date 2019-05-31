<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\RabbitMQ;

use Bunny\Channel;
use Bunny\Client;
use Cdn77\RabbitMQBundle\Configuration;
use Cdn77\RabbitMQBundle\Exception\CannotCreateChannel;
use Cdn77\RabbitMQBundle\Exception\ConnectionFailed;
use React\Promise\PromiseInterface;
use Throwable;

final class BunnyConnection implements Connection
{
    /** @var Client */
    private $client;

    /** @var Channel|null */
    private $channel;

    /** @var Channel */
    private $transactionalChannel;

    public function __construct(Configuration\Connection $configuration)
    {
        $options = [
            'host' => $configuration->getHost(),
            'port' => $configuration->getPort(),
            'vhost' => $configuration->getVhost(),
            'heartbeat' => $configuration->getHeartbeat(),
            'timeout' => $configuration->getConnectionTimeout(),
            'read_write_timeout' => $configuration->getReadWriteTimeout(),
        ];

        if ($configuration->getUser() !== null) {
            $options['user'] = $configuration->getUser();
        }

        if ($configuration->getPassword() !== null) {
            $options['password'] = $configuration->getPassword();
        }

        $this->client = new Client($options);
    }

    public function getChannel() : Channel
    {
        if ($this->channel === null) {
            $this->channel = $this->createChannel();
        }

        return $this->channel;
    }

    public function getTransactionalChannel() : Channel
    {
        if ($this->transactionalChannel === null) {
            $this->transactionalChannel = $this->createChannel();

            try {
                $this->transactionalChannel->txSelect();
            } catch (Throwable $exception) {
                throw new CannotCreateChannel('Cannot create transaction channel', 0, $exception);
            }
        }

        return $this->transactionalChannel;
    }

    public function connect() : void
    {
        if ($this->client->isConnected()) {
            return;
        }

        try {
            $this->client->connect();
        } catch (Throwable $exception) {
            throw ConnectionFailed::causedBy($exception);
        }
    }

    public function disconnect() : void
    {
        if (! $this->client->isConnected()) {
            return;
        }

        $this->client->disconnect();
        $this->channel = null;
    }

    private function createChannel() : Channel
    {
        $this->connect();

        $channel = $this->client->channel();

        if ($channel instanceof PromiseInterface) {
            throw CannotCreateChannel::gotInvalidType(Channel::class, PromiseInterface::class);
        }

        return $channel;
    }
}
