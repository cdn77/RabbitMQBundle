
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

Both limits end the run between messages, never in the middle of one: a `consume()` that is still
running when `maxSeconds` falls due is left to finish, and its acknowledge goes out on a connection
that is still open.

Consumer is registered under the name specified in `getName()` method. You can check whether it is successfully registered through `debug:rabbitmq:consumers` command. It can be run with `rabbitmq:consumer:run example_consumer`

> **Heads up (Bunny 0.6): a `consume()` that blocks for longer than the heartbeat loses the
> connection.** The event loop only turns while something awaits it, and a handler is ordinary
> synchronous PHP - a subprocess, an SFTP upload, a long HTTP call - so no heartbeat frame can be
> written for as long as it runs. The broker hangs up after two missed intervals, and the consumer
> then dies with `Exception\ConnectionFailed` saying the channel closed with no error reported.
>
> Worse than the exception: an acknowledge issued *after* that point is a write with no reply to
> wait for, so it silently goes nowhere. The message stays unacknowledged, the broker requeues it,
> and the handler runs again on the next consumer - work you may have thought was committed.
>
> A message *published* from inside a handler does not share that fate: the bundle puts it on the
> socket before `handle()` returns, so a handler that publishes and then blocks still gets it out.
> The two are therefore not atomic - if the connection goes while the handler works on, the
> published message stands while the consumed one is requeued and handled again.
>
> So a handler that can take minutes needs a `heartbeat` comfortably above twice its worst case
> (`heartbeat: 3600` for handlers measured in minutes), or it needs to stop blocking the loop.
> Acknowledging *before* the long stretch, rather than after it, also keeps the acknowledge on a
> connection that is still alive - at the cost of at-most-once delivery for that message.
