<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\Console;

use Cdn77\RabbitMQBundle\Configuration\Topology;
use Cdn77\RabbitMQBundle\DependencyInjection\RabbitMQExtension;
use Cdn77\RabbitMQBundle\SetupAction;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class SetUpCommand extends Command
{
    private const NAME = RabbitMQExtension::ALIAS . ':setup';
    private const DESCRIPTION = 'Set up exchanges and queues from configuration';

    /** @var SetupAction */
    private $setupAction;

    /** @var Topology */
    private $topology;

    public function __construct(SetupAction $setupAction, Topology $topology)
    {
        $this->setupAction = $setupAction;
        $this->topology = $topology;

        parent::__construct();
    }

    protected function configure() : void
    {
        $this->setName(self::NAME);
        $this->setDescription(self::DESCRIPTION);
    }

    protected function execute(InputInterface $input, OutputInterface $output) : void
    {
        $this->setupAction->setup($this->topology);
    }
}
