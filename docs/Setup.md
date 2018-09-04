# Installation

This bundle uses `rabbitmq` container extension key and is able to merge configurations from multiple files.

These configuration options are available to setup connection to your RabbitMQ instance. 

Example DSN: `amqp://username:password@host:1234/vhost?heartbeat=60&connection_timeout=10&read_write_timeout=3`

```yaml
rabbitmq:
    dsn: '%env(RABBITMQ_DSN)%'
```

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
