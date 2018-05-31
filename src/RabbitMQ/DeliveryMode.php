<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\RabbitMQ;

use Consistence\Enum\Enum;

final class DeliveryMode extends Enum
{
    public const TRANSIENT = 1;
    public const PERSISTENT = 2;
}
