# CDN77 RabbitMQ Bundle

[![Build Status](https://travis-ci.org/cdn77/RabbitMQBundle.svg)](https://travis-ci.org/cdn77/RabbitMQBundle)
[![Downloads](https://poser.pugx.org/cdn77/rabbitmq-bundle/d/total.svg)](https://packagist.org/packages/cdn77/rabbitmq-bundle)
[![Packagist](https://poser.pugx.org/cdn77/rabbitmq-bundle/v/stable.svg)](https://packagist.org/packages/cdn77/rabbitmq-bundle)
[![Licence](https://poser.pugx.org/cdn77/rabbitmq-bundle/license.svg)](https://packagist.org/packages/cdn77/rabbitmq-bundle)
[![Quality Score](https://scrutinizer-ci.com/g/cdn77/RabbitMQBundle/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/cdn77/RabbitMQBundle)
[![Code Coverage](https://scrutinizer-ci.com/g/cdn77/RabbitMQBundle/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/cdn77/RabbitMQBundle)

This bundle provides following commands:

- `rabbitmq:setup` – Creates exchanges, queues and bindings between them as specified in [configuration](#setup) in your RabbitMQ instance.
- `rabbitmq:consumer:run <consumer name>` – Runs specified consumer
- `debug:rabbitmq:consumers` – Displays registered consumers

For further information see following sections

- [Installation](docs/Installation.md)
- [Setup](docs/Setup.md)
- [Consuming](docs/Consuming.md)
- [Producing](docs/Producing.md)
