# Installation

Add as a dependency via Composer:

```sh
composer require cdn77/rabbitmq-bundle
```

> **Note:** This bundle requires `bunny/bunny` `0.6`, which is currently only available as a
> pre-release (`alpha`). Until a stable `0.6.0` is tagged, your project must allow that stability,
> e.g. by setting in your `composer.json`:
>
> ```json
> {
>     "minimum-stability": "alpha",
>     "prefer-stable": true
> }
> ```
>
> Bunny `0.6` runs on a [ReactPHP](https://reactphp.org/) event loop using PHP Fibers, so the
> bundle pulls in `react/event-loop`, `react/async` and `react/promise` as well. All broker I/O is
> driven synchronously for you — see [Producing](Producing.md) for the one caveat in long-lived CLI
> scripts.

> **Upgrading from a Bunny `0.5` release of this bundle:** Bunny `0.6` has no read/write timeout, so
> the `read_write_timeout` option is gone. A `read_write_timeout` query parameter in your DSN is
> simply ignored, but the YAML key now fails the container build with
> `Unrecognized option "read_write_timeout" under "rabbitmq"` — drop it from your configuration.
>
> Note also that Bunny `0.6` cannot recover within the same process from a *first* connection
> attempt that failed (the broker being unreachable at start-up); every later attempt on that
> connection reports `ConnectionFailed`. Losing an already established connection is handled and
> reconnects on the next operation.

If you're not using Symfony Flex, you will also need to enable the bundle by adding `Cdn77RabbitMQBundle` to `bundles.php`, that is required by `registerBundles()` in your `Kernel`:

```php
use Cdn77\RabbitMQBundle\Cdn77RabbitMQBundle;

return [
    ...
    Cdn77RabbitMQBundle::class => ['all' => true],
    ...
]
```
