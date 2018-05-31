<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\RabbitMQ;

use Consistence\Enum\Enum;

final class ExchangeType extends Enum
{
    public const DIRECT = 'direct';
    public const FANOUT = 'fanout';
    public const TOPIC = 'topic';
    public const HEADER = 'header';
}
