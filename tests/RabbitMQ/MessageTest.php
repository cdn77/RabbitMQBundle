<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\Tests\RabbitMQ;

use Cdn77\RabbitMQBundle\RabbitMQ\DeliveryMode;
use Cdn77\RabbitMQBundle\RabbitMQ\Message;
use PHPUnit\Framework\TestCase;

final class MessageTest extends TestCase
{
    public function testDeliveryMode() : void
    {
        $message = new Message('body');
        self::assertArrayHasKey(Message::HEADER_DELIVERY_MODE, $message->headers);
        self::assertSame($message->headers[Message::HEADER_DELIVERY_MODE], DeliveryMode::PERSISTENT);

        $message->makeTransient();
        self::assertArrayHasKey(Message::HEADER_DELIVERY_MODE, $message->headers);
        self::assertSame($message->headers[Message::HEADER_DELIVERY_MODE], DeliveryMode::TRANSIENT);
    }

    public function testJson() : void
    {
        $message = Message::json('json string');

        self::assertArrayHasKey('Content-Type', $message->headers);
        self::assertSame('application/json', $message->headers['Content-Type']);
    }
}
