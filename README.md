# CDN77 RabbitMQ Bundle

[![Build Status](https://github.com/cdn77/RabbitMQBundle/workflows/CI/badge.svg?branch=master)](https://github.com/cdn77/RabbitMQBundle/actions)
[![Coverage Status](https://coveralls.io/repos/github/cdn77/RabbitMQBundle/badge.svg?branch=master)](https://coveralls.io/github/cdn77/RabbitMQBundle?branch=master)
[![Downloads](https://poser.pugx.org/simpod/clickhouse-client/d/total.svg)](https://packagist.org/packages/simpod/clickhouse-client)

This bundle provides following commands:

- `rabbitmq:setup` – Creates exchanges, queues and bindings between them as specified in [configuration](#setup) in your RabbitMQ instance.
- `rabbitmq:consumer:run <consumer name>` – Runs specified consumer
- `debug:rabbitmq:consumers` – Displays registered consumers

For further information see following sections

- [Installation](docs/Installation.md)
- [Setup](docs/Setup.md)
- [Consuming](docs/Consuming.md)
- [Producing](docs/Producing.md)
