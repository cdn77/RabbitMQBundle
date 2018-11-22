# Producing

Producing is very simple and done through [`PublishOperation`](../src/RabbitMQ/Operation/PublishOperation.php) that is registered as a service and autowired by default by the bundle. The _publish_ method API requires you to pass _Connection_. It's ready for multiple-connection support that comes in a future.

```php
use Cdn77\RabbitMQBundle\RabbitMQ\Connection;
use Cdn77\RabbitMQBundle\RabbitMQ\Message;
use Cdn77\RabbitMQBundle\RabbitMQ\Operation\PublishOperation;

final class ExampleProducer
{
    /** @var PublishOperation */
    private $publishOperation;

    public function __construct(Connection $connection, PublishOperation $publishOperation)
    {
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
            $this->connection
            $message,
            $routingKey,
            'default_exchange', // Exchange to send the message to
        );
    }
}
```
