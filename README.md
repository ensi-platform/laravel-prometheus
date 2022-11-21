# Prometheus client for laravel

[![tests](https://github.com/MadridianFox/laravel-prometheus/actions/workflows/tests.yml/badge.svg)](https://github.com/MadridianFox/laravel-prometheus/actions/workflows/tests.yml)

Адаптер для [promphp/prometheus_client_php](https://github.com/PromPHP/prometheus_client_php)

## Installation

Добавьте пакет в приложение
```bash
composer require madridianfox/laravel-prometheus
```

Скопируйте конфигурацию для дальнейшей настройки
```bash
php artisan vendor:publish --tag=prometheus-config
```

## Usage

Перед тем как накручивать счётчики метрик, их надо зарегистрировать. Лучше всего это делать в методе boot() в AppServiceProvider.
```php
# app/Providers/AppServiceProvider.php
public function boot() {
    Prometheus::declareCounter('http_requests_count', ['endpoint', 'code']);
    Prometheus::declareSummary('http_requests_duration_seconds', 60, [0.5, 0.95, 0.99]);
}
```
Обновить значение счётчика так же просто
```php
# app/Http/Middleware/Telemetry.php
public function handle($request, Closure $next)
{
    $startTime = microtime(true);
    $response = $next($request);
    $endTime = microtime(true);
    
    Prometheus::updateCounter('http_requests_count', [Route::current()?->uri, $response->status()]);
    Prometheus::updateSummary('http_requests_duration_seconds', [], $endTime - $startTime);
    
    return $response;
}
```

## Configuration

Структура файла конфигурации

```php
# config/prometheus.php
return [
    'default_bag' => '<bag-name>',
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
                '<middlewares>'
            ]
        ],
    ],
];
```

**Bag**

Вы можете захотеть иметь несколько наборов метрик, например один набор с техническими метриками, вроде количества http запросов или непойманных исключений,
и второй для бизнес-значений вроде количества заказов или показов определённой страницы.
Для этого вводится понятие bag. 
Вы можете настроить несколько бэгов, указав для каждого своё хранилище данных, отдельный эндпоинт для сбора метрик и т.д.

**Storage type**

Вы можете использовать все хранилища (Adapters) из пакета promphp/prometheus_client_php. Кроме того вы можете указать имя
redis connection'a из файла `config/databases.php`.

Варианты настройки хранилища.  
Хранить метрики в памяти процесса.
```php
'memory' => true
```
Использовать APCu
```php
'apcu' => [
    'prefix' => 'metrics'
]
```
или альтернативный адаптер APCuNG
```php
'apcu-ng' => [
    'prefix' => 'metrics'
]
```
Redis адаптер, который сам создаст phpredis соединение
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
Laravel Redis соединение из `config/databases.php`. Под капотом будет создан тот же Redis адаптер,
но он возьмёт нативный объект соединения phpredis из RedisManager'a ларавеля.
```php
'connection' => [
    'connection' => 'default',
    'bag' => 'default',
]
```
## Advanced Usage
Выбрать другой bag для создания и обновления в нём метрик можно через метод `bag($bagName)`.
```php
# app/Providers/AppServiceProvider.php
public function boot() {
    // создаём метрики в конкретном bag
    Prometheus::bag('business')->declareCounter('orders_count', ['delivery_type', 'payment_method'])
}

# app/Actions/CreateOrder.php
public function execute(Order $order) {
    // ...
    Prometheus::bag('business')->updateCounter('orders_count', [$order->delivery_type, $order->payment_method]);
}
```

**Label Middlewares**

Вы можете добавить лейбл ко всем метрикам bag'a указав в его конфигурации т.н. Label middleware. Label middleware 
срабатывает в момент определения метрики и в момент обновления её счётчика, добавляя в первом случае на название лейбла, 
а во втором значение.  

Например у намс есть TenantLabelProvider
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
Регистрируем его в конфигурации bag'a.
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
Далее как обычно работаем с метриками.
```php
Prometheus::declareCounter('http_requests_count', ['endpoint', 'code']);
// ...
Prometheus::updateCounter('http_requests_count', [Route::current()?->uri, $response->status()]);
```
В результате метрика будет иметь не два, а три лейбла
```
app_http_requests_count{endpoint="catalog/products",code="200",tenant="JBZ-987-H6"} 987
```

## License
Laravel Prometheus is open-sourced software licensed under the [MIT license](LICENSE.md).