<?php

declare(strict_types=1);

namespace Cdn77\RabbitMQBundle\RabbitMQ;

class Message
{
    public const HEADER_DELIVERY_MODE = 'Delivery-Mode';

    /** @var string */
    public $body;

    /** @var mixed[] */
    public $headers;

    /**
     * @param mixed[] $headers
     */
    public function __construct(string $body, array $headers = [])
    {
        $this->body = $body;

        if (! isset($headers[self::HEADER_DELIVERY_MODE])) {
            $headers[self::HEADER_DELIVERY_MODE] = DeliveryMode::PERSISTENT;
        }

        $this->headers = $headers;
    }

    /**
     * @param mixed[] $headers
     */
    public static function json(string $body, array $headers = []) : self
    {
        return new self($body, ['Content-Type' => 'application/json'] + $headers);
    }

    public function makeTransient() : self
    {
        $this->headers[self::HEADER_DELIVERY_MODE] = DeliveryMode::TRANSIENT;

        return $this;
    }
}
