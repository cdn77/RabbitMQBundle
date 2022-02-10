<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle;

use Bunny\Channel;
use Bunny\Client;
use Bunny\Message;
use Bunny\Protocol\MethodBasicQosOkFrame;
use Cdn77\RabbitMQBundle\Exception\ConfigurationFailed;
use Cdn77\RabbitMQBundle\RabbitMQ\Connection;
use Cdn77\RabbitMQBundle\RabbitMQ\Consumer\Configuration;
use Cdn77\RabbitMQBundle\RabbitMQ\Consumer\Consumer;

use function microtime;

final class ConsumerRunner
{
    /** @var Connection */
    private $connection;

    /** @var int */
    private $processedMessageCount = 0;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function run(Consumer $consumer): void
    {
        $channel = $this->connection->getChannel();
        $consumerConfiguration = $consumer->getConfiguration();

        $qosOk = $channel->qos($consumerConfiguration->getPrefetchSize(), $consumerConfiguration->getPrefetchCount());
        if (! $qosOk instanceof MethodBasicQosOkFrame) {
            throw ConfigurationFailed::invalidPrefetchValues();
        }

        $this->createConsumerOnChannel($channel, $consumer);
        $this->consumerRun($channel, $consumer);
    }

    private function createConsumerOnChannel(Channel $channel, Consumer $consumer): void
    {
        $channel->consume(
            function (Message $message, Channel $channel, Client $client) use ($consumer): void {
                $consumerConfig = $consumer->getConfiguration();
                $consumer->consume($message);

                $this->processedMessageCount++;

                if ($this->hasAnyMessageLeft($consumerConfig->getMaxMessages(), $this->processedMessageCount)) {
                    return;
                }

                $client->stop();
            },
            $consumer->getConfiguration()->getQueueName(),
            '',
            false,
            false,
            false,
            false,
            []
        );
    }

    private function consumerRun(Channel $channel, Consumer $consumer): void
    {
        $consumerConfiguration = $consumer->getConfiguration();
        $startTime = microtime(true);

        while ($this->shouldContinue($startTime, $consumerConfiguration)) {
            $channel->getClient()->run($consumerConfiguration->getMaxSeconds());
        }
    }

    private function shouldContinue(float $startTime, Configuration $consumerConfiguration): bool
    {
        return $this->isInfinitelyRepeated($consumerConfiguration) ||
            ! $this->isLimitReached($startTime, $consumerConfiguration);
    }

    private function isInfinitelyRepeated(Configuration $consumerConfiguration): bool
    {
        return $consumerConfiguration->getMaxSeconds() === null && $consumerConfiguration->getMaxMessages() === null;
    }

    private function isLimitReached(float $startTime, Configuration $consumerConfiguration): bool
    {
        return ! $this->hasAnyTimeLeft($consumerConfiguration->getMaxSeconds(), $startTime)
            || ! $this->hasAnyMessageLeft($consumerConfiguration->getMaxMessages(), $this->processedMessageCount);
    }

    private function hasAnyTimeLeft(?float $maxSeconds, float $startTime): bool
    {
        return $maxSeconds === null || microtime(true) < $startTime + $maxSeconds;
    }

    private function hasAnyMessageLeft(?int $maxMessages, int $processedMessageCount): bool
    {
        return $maxMessages === null || $processedMessageCount < $maxMessages;
    }
}
