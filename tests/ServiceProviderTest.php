<?php

namespace Madridianfox\LaravelPrometheus\Tests;

use Illuminate\Support\Facades\Route;
use Madridianfox\LaravelPrometheus\PrometheusManager;

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