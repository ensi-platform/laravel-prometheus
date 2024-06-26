# Prometheus client for laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ensi/laravel-prometheus.svg?style=flat-square)](https://packagist.org/packages/ensi/laravel-prometheus)
[![Tests](https://github.com/ensi-platform/laravel-prometheus/actions/workflows/run-tests.yml/badge.svg?branch=master)](https://github.com/ensi-platform/laravel-prometheus/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/ensi/laravel-prometheus.svg?style=flat-square)](https://packagist.org/packages/ensi/laravel-prometheus)

Adapter for [promphp/prometheus_client_php](https://github.com/PromPHP/prometheus_client_php)

## Installation

You can install the package via composer:

```bash
composer require ensi/laravel-prometheus
```

Publish the config with:

```bash
php artisan vendor:publish --provider="Ensi\LaravelPrometheus\PrometheusServiceProvider"
```

## Version Compatibility

| Laravel Prometheus | Laravel                    | PHP  |
|--------------------|----------------------------|------|
| ^1.0.0             | ^9.x                       | ^8.1 |
| ^1.0.4             | ^9.x \|\| ^10.x            | ^8.1 |
| ^1.0.9             | ^9.x \|\| ^10.x \|\| ^11.x | ^8.1 |

## Basic Usage

Before you wind up the metric counters, you need to register them. The best thing to do is to use the boot() method from the application service provider.

```php
# app/Providers/AppServiceProvider.php
public function boot() {
    Prometheus::counter('http_requests_count')->labels(['endpoint', 'code']);
    Prometheus::summary('http_requests_duration_seconds', 60, [0.5, 0.95, 0.99]);
}
```
Updating the counter value is just as easy
```php
# app/Http/Middleware/Telemetry.php
public function handle($request, Closure $next)
{
    $startTime = microtime(true);
    $response = $next($request);
    $endTime = microtime(true);
    
    Prometheus::update('http_requests_count', 1, [Route::current()?->uri, $response->status()]);
    Prometheus::update('http_requests_duration_seconds', $endTime - $startTime);
    
    return $response;
}
```

## Configuration

The structure of the configuration file

```php
# config/prometheus.php
return [
    'default_bag' => '<bag-name>',
    'enabled' => env('PROMETHEUS_ENABLED', true),
    'app_name' => env('PROMETHEUS_APP_NAME', env('APP_NAME')),
    'bags' => [
        '<bag-name>' => [
            'namespace' => '<prometheus-namespace>',
            'route' => '<path-of-scrape-endpoint>',
            'basic_auth' => [
                'login' => env('PROMETHEUS_AUTH_LOGIN'),
                'password' => env('PROMETHEUS_AUTH_PASSWORD'),
            ],
            '<storage-type>' => [
                '<connection-parameters>'
            ],
            'label_middlewares' => [
                '<middleware-class>'
            ],
            'on_demand_metrics' => [
                '<on-demand-metric-class>'
            ]  
        ],
    ],
];
```

**Bag**

You may want to have several sets of metrics, for example, one set with technical metrics, such as the number of http requests or unexpected exceptions, and a second set for business values, such as the number of orders or impressions of a particular page.
To do this, the concept of bag is introduced.
You can configure several bugs by specifying your own data warehouse for each, a separate endpoint for collecting metrics, etc.

**Storage type**

You can use all the storage (Adapters) from the promphp/prometheus_client_php package. In addition, you can specify the name of the redis connection from the file `config/databases.php`.

Storage configuration options.  
Store metrics in the process memory.
```php
'memory' => true
```
Use apcupsd
```php
'apcu' => [
    'prefix' => 'metrics'
]
```
or an alternative APCuNG adapter
```php
'apcu-ng' => [
    'prefix' => 'metrics'
]
```
A Redis adapter that will create a phpredis connection by itself
```php
'redis' => [
    'host' => '127.0.0.1',
    'port' => 6379,
    'timeout' => 0.1,
    'read_timeout' => '10',
    'persistent_connections' => false,
    'password' => null,
    'prefix' => 'my-app',
    'bag' => 'my-metrics-bag'
]
```
Laravel Redis connection from `config/databases.php`. The same Redis adapter will be created under the hood, but it will take the native phpredis connection object from laravel's Redismanager.
```php
'connection' => [
    'connection' => 'default',
    'bag' => 'default',
]
```
## Advanced Usage

You can select another bag to create and update metrics in it using the `bag($bagName)` method.
```php
# app/Providers/AppServiceProvider.php
public function boot() {
    // создаём метрики в конкретном bag
    Prometheus::bag('business')->counter('orders_count')->labels(['delivery_type', 'payment_method'])
}

# app/Actions/CreateOrder.php
public function execute(Order $order) {
    // ...
    Prometheus::bag('business')->update('orders_count', 1, [$order->delivery_type, $order->payment_method]);
}
```

### Label Middlewares

You can add a label to all bagmetrics by specifying the so-called Label middleware in its configuration. Label middleware is triggered at the moment the metric is determined and at the moment its counter is updated, adding in the first case to the label name, and in the second case the value.

For example, we have a TenantLabelProvider
```php
class TenantLabelMiddleware implements LabelMiddleware
{
    public function labels(): array
    {
        return ['tenant'];
    }

    public function values(): array
    {
        return [Tenant::curent()->id];
    }
}
```
We register it in the bag configuration.
```php
# config/prometheus.php
return [
    // ...
    'bags' => [
        'default' => [
            // ...
            'label_middlewares' => [
                \App\System\TenantLabelMiddleware::class,
            ]
        ],
    ],
];
```
Then, as usual, we work with metrics.
```php
Prometheus::counter('http_requests_count')->labels(['endpoint', 'code']);
// ...
Prometheus::update('http_requests_count', 1, [Route::current()?->uri, $response->status()]);
```
As a result, the metric will have not two, but three labels
```
app_http_requests_count{endpoint="catalog/products",code="200",tenant="JBZ-987-H6"} 987
```

### On demand metrics

Sometimes metrics are not linked to application events. Usually these are metrics of the gauge type, which it makes no sense to update on each incoming request, because prometheus will still take only the last set value.
Such metrics can be calculated at the time of collection of metrics by prometheus.
To do this, you need to create a so-called on demand metric. This is the class in which you register metrics and set values in them.
```php
class QueueLengthOnDemandMetric extends OnDemandMetric {
    public function register(MetricsBag $metricsBag): void
    {
        $metricsBag->gauge('queue_length');
    }

    public function update(MetricsBag $metricsBag): void
    {
        $metricsBag->update('queue_length', Queue::size());
    }
}
```
The update of such metrics occurs at the moment prometheus addresses the endpoint of obtaining metrics.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

### Testing

1. composer install
2. composer test

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
