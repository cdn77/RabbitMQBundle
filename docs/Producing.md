# Producing

Producing is very simple and done through [`PublishJsonOperation`](../src/RabbitMQ/Operation/PublishJsonOperation.php) that is registered as a service and autowired by default by the bundle.

```php
use Cdn77\RabbitMQBundle\RabbitMQ\Operation\PublishJsonOperation;

final class ExampleProducer
{
    /** @var PublishJsonOperation */
    private $publishJsonOperation;

    public function __construct(PublishJsonOperation $publishJsonOperation)
    {
        $this->publishJsonOperation = $publishJsonOperation;
    }

    /**
     * @param mixed[] $data
     */
    public function publishWithRoutingKey(array $data, string $routingKey) : void
    {
        $this->publishJsonOperation->handle(
            json_encode($data),
            $routingKey,
            'default_exchange', // Exchange to send the message to
            false // Messages are persistent by default but you can set 4th argument to `false` to make them non-persistent
        );
    }
}
```
