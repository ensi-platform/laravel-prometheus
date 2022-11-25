<?php

namespace Madridianfox\LaravelPrometheus\Tests;

use Illuminate\Http\Request;
use Madridianfox\LaravelPrometheus\Controllers\MetricsController;
use Madridianfox\LaravelPrometheus\PrometheusManager;
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