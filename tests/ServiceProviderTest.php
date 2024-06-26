<?php

namespace Ensi\LaravelPrometheus\Tests;

use Ensi\LaravelPrometheus\PrometheusManager;
use Illuminate\Support\Facades\Route;

use function PHPUnit\Framework\assertInstanceOf;
use function PHPUnit\Framework\assertTrue;

uses(TestCase::class);

test('test manager is registered', function () {
    assertInstanceOf(PrometheusManager::class, resolve(PrometheusManager::class));
});

test('test default bag route is registered', function () {
    assertTrue(Route::has('prometheus.default'));
});
