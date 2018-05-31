
# Consuming

Your consumer service should implement [`Consumer`](../src/RabbitMQ/Consumer/Consumer.php) interface so the bundle can register it as a consumer.

Acknowledgement is done through [`AcknowledgeOperation`](../src/RabbitMQ/Operation/AcknowledgeOperation.php). You can acknowledge one message with its `handle($message)`. In some cases, you might need to acknowledge multiple messages and once, you can use `handleAll($lastMessage)` for that.

There's [`RejectOperation`](../src/RabbitMQ/Operation/RejectOperation.php) for message rejection as well with `handle($message)` and `handleAll($lastMessage)`.
```php
use Bunny\Message;
use Cdn77\RabbitMQBundle\RabbitMQ\Consumer\Consumer;
use Cdn77\RabbitMQBundle\RabbitMQ\Consumer\Configuration;
use Cdn77\RabbitMQBundle\RabbitMQ\Operation\AcknowledgeOperation;
use Cdn77\RabbitMQBundle\RabbitMQ\Operation\RejectOperation;

final class ExampleConsumer implements Consumer
{
    private const QUEUE_NAME = 'some_queue';

    /** @var AcknowledgeOperation */
    private $acknowledgeOperation;

    /** @var RejectOperation */
    private $rejectOperation;

    public function __construct(AcknowledgeOperation $acknowledgeOperation, RejectOperation $rejectOperation)
    {
        $this->acknowledgeOperation = $acknowledgeOperation;
        $this->rejectOperation = $rejectOperation;
    }

    public function consume(Message $bunnyMessage) : void
    {
        $data = json_decode($bunnyMessage->content, true);
        
        try {
            // Do something with the message
        } catch(\Throwable $throwable) {
            $this->rejectOperation->handle($bunnyMessage);

            return;
        }

        $this->acknowledgeOperation->handle($bunnyMessage);
    }

    public function getConfiguration() : Configuration
    {
        $prefetchCount = 1;
        $prefetchSize = 0;
        $maxMessages = 100;
        $maxSeconds = 1000;

        return new ConsumerConfiguration(self::QUEUE_NAME, $prefetchCount, $prefetchSize, $maxMessages, $maxSeconds);
    }

    public function getName() : string
    {
        return 'example_consumer';
    }
}
```

Consumer configuration is done via `getConfiguration()` that returns instance of [`Configuration`](../src/RabbitMQ/Consumer/Configuration.php).

`maxMessages` & `maxSeconds` parameters are recommended to set to something else than `null` so consumer will shutdown gracefully after specified number of messages is consumed or seconds elapsed so it can start with fresh memory again.

Consumer is registered under the name specified in `getName()` method. You can check whether it is successfully registered through `debug:rabbitmq:consumers` command. It can be run with `rabbitmq:consumer:run example_consumer`
