<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\RabbitMQ\Consumer;

final class Configuration
{
    /** @var string */
    private $queueName;

    /** @var int|null */
    private $maxMessages;

    /** @var float|null */
    private $maxSeconds;

    /** @var int */
    private $prefetchCount;

    /** @var int */
    private $prefetchSize;

    public function __construct(
        string $queueName,
        int $prefetchCount = 1,
        int $prefetchSize = 0,
        ?int $maxMessages = null,
        ?float $maxSeconds = null
    ) {
        $this->queueName = $queueName;
        $this->prefetchCount = $prefetchCount;
        $this->prefetchSize = $prefetchSize;
        $this->maxMessages = $maxMessages;
        $this->maxSeconds = $maxSeconds;
    }

    public function getQueueName() : string
    {
        return $this->queueName;
    }

    public function getPrefetchCount() : int
    {
        return $this->prefetchCount;
    }

    public function getPrefetchSize() : int
    {
        return $this->prefetchSize;
    }

    public function getMaxMessages() : ?int
    {
        return $this->maxMessages;
    }

    public function getMaxSeconds() : ?float
    {
        return $this->maxSeconds;
    }
}
