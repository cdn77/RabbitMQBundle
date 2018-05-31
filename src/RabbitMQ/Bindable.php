<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\RabbitMQ;

interface Bindable
{
    public function getName() : string;
}
