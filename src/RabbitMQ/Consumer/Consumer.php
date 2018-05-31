<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\RabbitMQ\Consumer;

use Bunny\Message;

interface Consumer
{
    public function consume(Message $message) : void;

    public function getConfiguration() : Configuration;

    public function getName() : string;
}
