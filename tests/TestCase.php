<?php

namespace Madridianfox\LaravelPrometheus\Tests;

class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            \Madridianfox\LaravelPrometheus\PrometheusServiceProvider::class,
        ];
    }
}