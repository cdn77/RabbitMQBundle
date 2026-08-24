<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\RabbitMQ;

use MyCLabs\Enum\Enum;

/** @extends Enum<string> */
final class ExchangeType extends Enum
{
    public const string DIRECT = 'direct';
    public const string FANOUT = 'fanout';
    public const string TOPIC = 'topic';
    public const string HEADER = 'header';
}
