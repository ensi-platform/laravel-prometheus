<?php

namespace Ensi\LaravelPrometheus\Tests;

use Ensi\LaravelPrometheus\Controllers\MetricsController;
use Ensi\LaravelPrometheus\PrometheusManager;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MetricsControllerTest extends TestCase
{
    public function testMetricsRoute()
    {
        config([
            'prometheus.bags' => [
                'default' => [
                    'namespace' => 'app',
                    'route' => 'metrics',
                    'memory' => true,
                ]
            ]
        ]);

        /** @var PrometheusManager $manager */
        $manager = resolve(PrometheusManager::class);
        $request = Request::create("http://localhost/metrics");

        $manager->counter('orders_count')->update();

        $response = (new MetricsController())($request, $manager);
        $this->assertStringContainsString('app_orders_count', $response->getContent());
    }

    public function testMetricsRouteWithAuth()
    {
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
                ]
            ]
        ]);

        /** @var PrometheusManager $manager */
        $manager = resolve(PrometheusManager::class);
        $request = Request::create("http://user:password@localhost/metrics");

        $response = (new MetricsController())($request, $manager);
        $this->assertEquals(200, $response->status());
    }

    public function testMetricsRouteAccessDenied()
    {
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
                ]
            ]
        ]);

        /** @var PrometheusManager $manager */
        $manager = resolve(PrometheusManager::class);
        $request = Request::create("http://localhost/metrics");

        $this->assertThrows(fn () => (new MetricsController())($request, $manager), HttpException::class);
    }
}