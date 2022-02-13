<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\Console;

use Cdn77\RabbitMQBundle\DependencyInjection\ConsumerStorage;
use Cdn77\RabbitMQBundle\DependencyInjection\RabbitMQExtension;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ConsumerListCommand extends Command
{
    private const NAME = 'debug:' . RabbitMQExtension::ALIAS . ':consumers';
    private const DESCRIPTION = 'Show list of defined consumers.';

    /** @var ConsumerStorage */
    private $consumerStorage;

    public function __construct(ConsumerStorage $consumerStorage)
    {
        $this->consumerStorage = $consumerStorage;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName(self::NAME)
            ->setDescription(self::DESCRIPTION);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach ($this->consumerStorage->getConsumers() as $consumerName => $consumer) {
            $output->writeln($consumerName);
        }

        return 0;
    }
}
