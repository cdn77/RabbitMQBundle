# Installation

Add as a dependency via Composer:

```sh
composer require cdn77/rabbitmq-bundle
```

If you're not using Symfony Flex, you will also need to enable the bundle by adding `Cdn77RabbitMQBundle` to `bundles.php`, that is required by `registerBundles()` in your `Kernel`:

```php
use Cdn77\RabbitMQBundle\Cdn77RabbitMQBundle;

return [
    ...
    Cdn77RabbitMQBundle::class => ['all' => true],
    ...
]
```
