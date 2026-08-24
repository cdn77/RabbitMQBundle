<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\RabbitMQ;

use MyCLabs\Enum\Enum;

/** @extends Enum<int> */
final class DeliveryMode extends Enum
{
    public const int TRANSIENT = 1;
    public const int PERSISTENT = 2;
}
