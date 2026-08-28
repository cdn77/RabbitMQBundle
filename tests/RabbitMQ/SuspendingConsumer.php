<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\Tests\RabbitMQ;

use Bunny\Message;
use Cdn77\RabbitMQBundle\RabbitMQ\Consumer\Configuration;
use Cdn77\RabbitMQBundle\RabbitMQ\Consumer\Consumer;
use Cdn77\RabbitMQBundle\RabbitMQ\Operation\AcknowledgeOperation;
use RuntimeException;

use function React\Async\delay;

/**
 * A handler that suspends its own Fiber, the way any real one does when it publishes a batch, gets a
 * message or waits on anything else - and so lets the run's time limit fall due while it holds the
 * message.
 */
final class SuspendingConsumer implements Consumer
{
    public const string EXCEPTION_MESSAGE = 'Consumer blew up after suspending';

    /** @var AcknowledgeOperation */
    private $acknowledgeOperation;

    /** @var Configuration */
    private $configuration;

    /** @var float */
    private $suspendForSeconds;

    /** @var bool */
    private $throwWhenResumed;

    public function __construct(
        AcknowledgeOperation $acknowledgeOperation,
        Configuration $configuration,
        float $suspendForSeconds,
        bool $throwWhenResumed = false,
    ) {
        $this->acknowledgeOperation = $acknowledgeOperation;
        $this->configuration = $configuration;
        $this->suspendForSeconds = $suspendForSeconds;
        $this->throwWhenResumed = $throwWhenResumed;
    }

    public function consume(Message $message): void
    {
        delay($this->suspendForSeconds);

        if ($this->throwWhenResumed) {
            throw new RuntimeException(self::EXCEPTION_MESSAGE);
        }

        $this->acknowledgeOperation->handle($message);
    }

    public function getName(): string
    {
        return 'suspending';
    }

    public function getConfiguration(): Configuration
    {
        return $this->configuration;
    }
}
