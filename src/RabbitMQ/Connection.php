<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\RabbitMQ;

use Bunny\Channel;

interface Connection
{
    public function getChannel(): Channel;

    public function getTransactionalChannel(): Channel;

    public function connect(): void;

    public function disconnect(): void;
}
