<?php

namespace Ensi\LaravelPrometheus\Tests;

class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            \Ensi\LaravelPrometheus\PrometheusServiceProvider::class,
        ];
    }
}
