<?php

namespace Ensi\LaravelPrometheus\Tests;

use Ensi\LaravelPrometheus\PrometheusManager;
use Illuminate\Support\Facades\Route;

class ServiceProviderTest extends TestCase
{
    public function testManagerIsRegistered()
    {
        self::assertInstanceOf(PrometheusManager::class, resolve(PrometheusManager::class));
    }

    public function testDefaultBagRouteIsRegistered()
    {
        $this->assertTrue(Route::has('prometheus.default'));
    }
}
