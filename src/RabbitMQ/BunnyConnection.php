<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\RabbitMQ;

use Bunny\ChannelInterface;
use Bunny\Client;
use Bunny\Configuration as BunnyConfiguration;
use Bunny\Defaults;
use Cdn77\RabbitMQBundle\Configuration;
use Cdn77\RabbitMQBundle\Exception\CannotCreateChannel;
use Cdn77\RabbitMQBundle\Exception\ConnectionFailed;
use Closure;
use Throwable;

use function React\Async\async;
use function React\Async\await;

final class BunnyConnection implements Connection
{
    /** @var BunnyConfiguration */
    private $configuration;

    /** @var Client */
    private $client;

    /** @var ChannelInterface|null */
    private $channel;

    /** @var ChannelInterface|null */
    private $transactionalChannel;

    public function __construct(Configuration\Connection $configuration)
    {
        $this->configuration = new BunnyConfiguration(
            host: $configuration->getHost(),
            port: $configuration->getPort(),
            vhost: $configuration->getVhost(),
            user: $configuration->getUser() ?? Defaults::USER,
            password: $configuration->getPassword() ?? Defaults::PASSWORD,
            timeout: $configuration->getConnectionTimeout(),
            heartbeat: (float) $configuration->getHeartbeat(),
        );
        $this->client = new Client($this->configuration);
    }

    public function getChannel(): ChannelInterface
    {
        $channel = $this->channel;
        if ($channel === null) {
            $channel = $this->createChannel();
            $channel->once('close', function (): void {
                $this->channel = null;
            });

            $this->channel = $channel;
        }

        return $channel;
    }

    public function getTransactionalChannel(): ChannelInterface
    {
        if ($this->transactionalChannel === null) {
            $channel = $this->createChannel();
            $channel->once('close', function (): void {
                $this->transactionalChannel = null;
            });

            try {
                $channel->txSelect();
            } catch (Throwable $exception) {
                throw new CannotCreateChannel('Cannot create transaction channel', 0, $exception);
            }

            // Cache it only once it is transactional, otherwise a retry would hand back a plain
            // channel from the fast path above and fail on txCommit() instead.
            $this->transactionalChannel = $channel;
        }

        return $this->transactionalChannel;
    }

    public function connect(): void
    {
        if ($this->client->canDisconnect()) {
            return;
        }

        // Bunny never rolls the state back when connect() fails, leaving a client that reports
        // itself as connected yet refuses to connect again - so every later attempt would await a
        // promise nothing can resolve. Such a client is unusable; start over with a fresh one.
        if ($this->client->isConnected()) {
            $this->channel = null;
            $this->transactionalChannel = null;
            $this->client = new Client($this->configuration);
        }

        try {
            $this->run(function (): void {
                $this->client->connect();
            });
        } catch (Throwable $exception) {
            throw ConnectionFailed::causedBy($exception);
        }
    }

    public function disconnect(): void
    {
        // Bunny only tolerates disconnect() on a fully connected client - canDisconnect() also
        // rules out the connecting/disconnecting states that isConnected() reports as connected.
        if (! $this->client->canDisconnect()) {
            return;
        }

        $this->run(function (): void {
            $this->client->disconnect();
        });

        $this->channel = null;
        $this->transactionalChannel = null;
    }

    /**
     * @param Closure(): T $operation
     *
     * @return T
     *
     * @template T
     */
    public function run(Closure $operation): mixed
    {
        // Always on a Fiber of its own, even when one is already current (an acknowledge issued
        // from inside a consumer callback). Running the operation on the calling Fiber instead
        // saves an allocation, but makes PHP unable to switch back out of contexts that forbid it
        // - a signal handler above all - turning a survivable shutdown into a fatal FiberError.
        return await(async($operation)());
    }

    private function createChannel(): ChannelInterface
    {
        $this->connect();

        return $this->client->channel();
    }
}
