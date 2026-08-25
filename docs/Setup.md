# Installation

This bundle uses `rabbitmq` container extension key and is able to merge configurations from multiple files.

These configuration options are available to setup connection to your RabbitMQ instance. 

Example DSN: `amqp://username:password@host:1234/vhost?heartbeat=60&connection_timeout=10&operation_timeout=30`

```yaml
rabbitmq:
    dsn: '%env(RABBITMQ_DSN)%'
    heartbeat: 60           # seconds; also as a DSN parameter, where it wins over this
    connection_timeout: 10  # seconds to establish the connection
    operation_timeout: 30   # seconds a single broker operation may take before it is given up on
```

`operation_timeout` bounds every single operation - publishing, acknowledging, declaring topology,
connecting - but never the consume loop, which runs until the consumer's own message or time limit
is reached. Exceeding it replaces the connection and throws `Exception\OperationFailed`, rather than
leaving the process waiting for a broker that is not going to answer. Where the caller reports
failures in its own terms it keeps doing so, carrying the `OperationFailed` as the cause: connecting
throws `Exception\ConnectionFailed`, and `rabbitmq:setup` throws `Exception\ConfigurationFailed`
naming the exchange or queue it got stuck on. All of them implement `Exception\Exception`. Any value
of `0` or below disables the bound, at the risk of a process that can never finish.

A connection idle for longer than `heartbeat` is replaced before the next operation: the event loop
only turns while an operation is awaiting, so nothing can send a heartbeat frame in between, and the
broker will have closed such a connection already.

`heartbeat` has to be a positive number of seconds; `0`, which in AMQP switches heartbeats off, is
rejected. Bunny 0.6 arms the timer whatever the interval is and re-arms it with the same value, so
zero leaves a timer that is due again the moment it fires - spinning the event loop at a full core
for as long as any operation is awaiting, and flooding the broker with heartbeat frames. Configure a
long interval (say `3600`) if heartbeats are genuinely not wanted.

Exchanges and Queues configuration can be done this way

```yaml
rabbitmq:
    exchanges:
        default_exchange:           # Exchange name
            durable: true
            auto_delete: false
            internal: false
            type: topic             # Types available: direct, topic, fanout, headers | see https://www.rabbitmq.com/tutorials/amqp-concepts.html#exchanges
            arguments:
                -   key: value
                -   key2: value2
        
        logging_exchange:
            durable: true
            type: topic
            bindings:
                -   exchange: default_exchange  # RabbitMQ-specific functionality = exchange-to-exchange bindings
                    routing_key: "#" # Placeholders can be used in routing keys: * (star) can substitute for exactly one word, # (hash) can substitute for zero or more words
  
    queues:
        some_queue:                             # Queue name
            durable: true
            exclusive: true
            auto_delete: true
            arguments:
                -   key: value
                -   key2: value2
            bindings:
                -   exchange: default_exchange
                    routing_key: "some_routing_key"
```

Setup command is available to configure Exchanges and Queues according to configuration defined in yaml as shown above.

```sh
$ bin/console rabbitmq:setup
```
