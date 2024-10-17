<?php

namespace Ensi\LaravelPrometheus\Tests;

use Ensi\LaravelPrometheus\PrometheusServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            PrometheusServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('prometheus.bags.default.connection', ['connection' => 'default', 'bag' => 'default']);
        config()->set('prometheus.enabled', true);
    }
}
