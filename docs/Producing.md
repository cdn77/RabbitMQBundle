# Producing

Producing is very simple and done through [`PublishOperation`](../src/RabbitMQ/Operation/PublishOperation.php) that is registered as a service and autowired by default by the bundle. The _publish_ method API requires you to pass _Connection_. It's ready for multiple-connection support that comes in a future.

```php
use Cdn77\RabbitMQBundle\RabbitMQ\Connection;
use Cdn77\RabbitMQBundle\RabbitMQ\Message;
use Cdn77\RabbitMQBundle\RabbitMQ\Operation\PublishOperation;

final class ExampleProducer
{
    /** @var Connection */
    private $connection;

    /** @var PublishOperation */
    private $publishOperation;

    public function __construct(Connection $connection, PublishOperation $publishOperation)
    {
        $this->connection = $connection;
        $this->publishOperation = $publishOperation;
    }

    /**
     * @param mixed[] $data
     */
    public function publishWithRoutingKey(array $data, string $routingKey) : void
    {
        $message = Message::json(json_encode($data));
        
        // Messages are persistent by default but you can make them transient so they don't persist
        $message->makeTransient();

        $this->publishOperation->handle(
            $this->connection,
            $message,
            $routingKey,
            'default_exchange', // Exchange to send the message to
        );
    }
}
```

> **Heads up (Bunny 0.6):** while connected, Bunny keeps a heartbeat timer on the ReactPHP event
> loop, which would otherwise keep the php-fpm worker or console process alive after the work is
> done. The bundle ships a `DisconnectConnection` subscriber that closes the connection on
> `kernel.terminate` (after the HTTP response is flushed) and on `console.terminate` (after a
> command returns), so HTTP and console producers are handled automatically. If you publish from a
> context that dispatches neither event (e.g. a bare script using the event loop directly), call
> `$connection->disconnect()` yourself when you're done.

> **Heads up (Bunny 0.6):** a single `handle()` is fire-and-forget - the broker sends no reply to
> wait for - so it tells you the message was written to the socket, not that the broker has it. The
> bundle does make sure of the writing: publishing awaits nothing, so the event loop would otherwise
> never turn and the message would sit in a buffer until some later operation happened to flush it,
> which for a producer of ordinary messages could be never. Use `handleAll()` when you need to know
> the broker has the messages before you carry on: it publishes in a transaction and returns once
> the commit is confirmed.
