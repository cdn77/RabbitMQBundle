<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\Exception;

use RuntimeException;

final class OperationFailed extends RuntimeException implements Exception
{
}
