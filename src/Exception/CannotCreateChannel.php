<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\Exception;

use RuntimeException;

final class CannotCreateChannel extends RuntimeException implements Exception
{
}
