# CDN77 RabbitMQ Bundle

[![GitHub Actions][GA Image]][GA Link]
[![Shepherd Type][Shepherd Image]][Shepherd Link]
[![Code Coverage][Coverage Image]][CodeCov Link]
[![Downloads][Downloads Image]][Packagist Link]
[![Packagist][Packagist Image]][Packagist Link]
[![Infection MSI][Infection Image]][Infection Link]

This bundle provides following commands:

- `rabbitmq:setup` – Creates exchanges, queues and bindings between them as specified in [configuration](#setup) in your RabbitMQ instance.
- `rabbitmq:consumer:run <consumer name>` – Runs specified consumer
- `debug:rabbitmq:consumers` – Displays registered consumers

For further information see following sections

- [Installation](docs/Installation.md)
- [Setup](docs/Setup.md)
- [Consuming](docs/Consuming.md)
- [Producing](docs/Producing.md)

[GA Image]: https://github.com/cdn77/RabbitMQBundle/workflows/CI/badge.svg

[GA Link]: https://github.com/cdn77/RabbitMQBundle/actions?query=workflow%3A%22CI%22+branch%3Amaster

[Shepherd Image]: https://shepherd.dev/github/cdn77/RabbitMQBundle/coverage.svg

[Shepherd Link]: https://shepherd.dev/github/cdn77/RabbitMQBundle

[Coverage Image]: https://codecov.io/gh/cdn77/RabbitMQBundle/branch/master/graph/badge.svg

[CodeCov Link]: https://codecov.io/gh/cdn77/RabbitMQBundle/branch/master

[Downloads Image]: https://poser.pugx.org/cdn77/rabbitmq-bundle/d/total.svg

[Packagist Image]: https://poser.pugx.org/cdn77/rabbitmq-bundle/v/stable.svg

[Packagist Link]: https://packagist.org/packages/cdn77/rabbitmq-bundle

[Infection Image]: https://badge.stryker-mutator.io/github.com/cdn77/RabbitMQBundle/master

[Infection Link]: https://dashboard.stryker-mutator.io/reports/github.com/cdn77/RabbitMQBundle/master
