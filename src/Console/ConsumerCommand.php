<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\Console;

use Cdn77\RabbitMQBundle\ConsumerRunner;
use Cdn77\RabbitMQBundle\DependencyInjection\ConsumerStorage;
use Cdn77\RabbitMQBundle\DependencyInjection\RabbitMQExtension;
use Cdn77\RabbitMQBundle\Exception\ConsumerFailed;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function assert;
use function is_string;
use function strtolower;

final class ConsumerCommand extends Command
{
    private const NAME = RabbitMQExtension::ALIAS . ':consumer:run';
    private const DESCRIPTION = 'Starts given consumer.';
    private const CONSUMER_ARGUMENT_NAME = 'consumerName';
    private const CONSUMER_ARGUMENT_DESCRIPTION = 'Name of consumer.';

    /** @var ConsumerStorage */
    private $consumerStorage;

    /** @var ConsumerRunner */
    private $consumerRunner;

    public function __construct(ConsumerRunner $consumeProcess, ConsumerStorage $consumerStorage)
    {
        parent::__construct();

        $this->consumerRunner = $consumeProcess;
        $this->consumerStorage = $consumerStorage;
    }

    protected function configure(): void
    {
        $this->setName(self::NAME);
        $this->setDescription(self::DESCRIPTION);
        $this->addArgument(
            self::CONSUMER_ARGUMENT_NAME,
            InputArgument::REQUIRED,
            self::CONSUMER_ARGUMENT_DESCRIPTION
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $consumerNameArgument = $input->getArgument(self::CONSUMER_ARGUMENT_NAME);
        assert(is_string($consumerNameArgument));

        $consumerName = strtolower($consumerNameArgument);
        $consumers = $this->consumerStorage->getConsumers();

        if (! isset($consumers[$consumerName])) {
            throw ConsumerFailed::doesNotExist($consumerName);
        }

        $consumer = $consumers[$consumerName];
        $this->consumerRunner->run($consumer);

        return 0;
    }
}
