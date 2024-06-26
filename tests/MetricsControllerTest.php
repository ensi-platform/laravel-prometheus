<?php

namespace Ensi\LaravelPrometheus\Tests;

use Ensi\LaravelPrometheus\Controllers\MetricsController;
use Ensi\LaravelPrometheus\PrometheusManager;
use Illuminate\Http\Request;

use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertStringContainsString;

use Symfony\Component\HttpKernel\Exception\HttpException;

uses(TestCase::class);

test('test metrics route', function () {
    config([
        'prometheus.bags' => [
            'default' => [
                'namespace' => 'app',
                'route' => 'metrics',
                'memory' => true,
            ],
        ],
    ]);

    /** @var PrometheusManager $manager */
    $manager = resolve(PrometheusManager::class);
    $request = Request::create("http://localhost/metrics");

    $manager->counter('orders_count')->update();

    $response = (new MetricsController())($request, $manager);
    assertStringContainsString('app_orders_count', $response->getContent());
});

test('test metrics route with auth', function () {
    config([
        'prometheus.bags' => [
            'default' => [
                'namespace' => 'app',
                'route' => 'metrics',
                'memory' => true,
                'basic_auth' => [
                    'login' => 'user',
                    'password' => 'password',
                ],
            ],
        ],
    ]);

    /** @var PrometheusManager $manager */
    $manager = resolve(PrometheusManager::class);
    $request = Request::create("http://user:password@localhost/metrics");

    $response = (new MetricsController())($request, $manager);
    assertEquals(200, $response->status());
});

test('test metrics route access denied', function () {
    config([
        'prometheus.bags' => [
            'default' => [
                'namespace' => 'app',
                'route' => 'metrics',
                'memory' => true,
                'basic_auth' => [
                    'login' => 'user',
                    'password' => 'password',
                ],
            ],
        ],
    ]);

    /** @var PrometheusManager $manager */
    $manager = resolve(PrometheusManager::class);
    $request = Request::create("http://localhost/metrics");

    $this->assertThrows(fn () => (new MetricsController())($request, $manager), HttpException::class);
});
