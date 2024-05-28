<?php

namespace Ensi\LaravelPrometheus\Tests;

use Ensi\LaravelPrometheus\MetricsBag;
use Ensi\LaravelPrometheus\PrometheusManager;
use InvalidArgumentException;
use Mockery\MockInterface;

use function PHPUnit\Framework\assertInstanceOf;
use function PHPUnit\Framework\assertNotSame;
use function PHPUnit\Framework\assertSame;

uses(TestCase::class);

test('test default bag is exists', function () {
    assertInstanceOf(MetricsBag::class, resolve(PrometheusManager::class)->bag());
});

test('test get bag by name', function () {
    config([
        'prometheus.bags' => [
            'first' => [1],
            'second' => [1],
        ],
    ]);
    $manager = resolve(PrometheusManager::class);
    assertInstanceOf(MetricsBag::class, $manager->bag('second'));
});

test('test undefined bag throws exception', function () {
    $this->assertThrows(function () {
        resolve(PrometheusManager::class)->bag('undefined');
    }, InvalidArgumentException::class);
});

test('test bag is not recreated at second call', function () {
    /** @var PrometheusManager $manager */
    $manager = resolve(PrometheusManager::class);

    $firstBag = $manager->bag();
    $secondBag = $manager->bag();

    assertSame($firstBag, $secondBag);
});

test('test set default bag', function () {
    config([
        'prometheus' => [
            'default_bag' => 'first',
            'bags' => [
                'first' => [1],
                'second' => [1],
            ],
        ],
    ]);
    /** @var PrometheusManager $manager */
    $manager = resolve(PrometheusManager::class);

    $firstBag = $manager->bag();
    $manager->setDefaultBag('second');
    $secondBag = $manager->bag();

    assertNotSame($firstBag, $secondBag);
});

test('test invoke default bags method on call', function () {
    /** @var PrometheusManager|MockInterface $manager */
    $manager = $this->partialMock(PrometheusManager::class)
        ->shouldAllowMockingProtectedMethods();

    $bag = $this->mock(MetricsBag::class);
    $bag->expects('declareCounter')
        ->withArgs(['example']);

    $manager->expects('createMetricsBag')
        ->withArgs(['default'])
        ->andReturn($bag);

    $manager->declareCounter('example');
});
