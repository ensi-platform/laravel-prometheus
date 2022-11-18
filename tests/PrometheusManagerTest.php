<?php

namespace Madridianfox\LaravelPrometheus\Tests;

use InvalidArgumentException;
use Madridianfox\LaravelPrometheus\MetricsBag;
use Madridianfox\LaravelPrometheus\PrometheusManager;
use Mockery\MockInterface;

class PrometheusManagerTest extends TestCase
{
    public function testDefaultBagIsExists()
    {
        $this->assertInstanceOf(MetricsBag::class, resolve(PrometheusManager::class)->bag());
    }

    public function testGetBagByName()
    {
        config([
            'prometheus.bags' => [
                'first' => [1],
                'second' => [1],
            ]
        ]);
        $manager = resolve(PrometheusManager::class);
        $this->assertInstanceOf(MetricsBag::class, $manager->bag('second'));
    }

    public function testUndefinedBagThrowsException()
    {
        $this->assertThrows(function () {
            resolve(PrometheusManager::class)->bag('undefined');
        }, InvalidArgumentException::class);
    }

    public function testBagIsNotRecreatedAtSecondCall()
    {
        /** @var PrometheusManager $manager */
        $manager = resolve(PrometheusManager::class);

        $firstBag = $manager->bag();
        $secondBag = $manager->bag();

        $this->assertSame($firstBag, $secondBag);
    }

    public function testDifferentDefaultBagsForDifferentContexts()
    {
        config([
            'prometheus' => [
                'defaults' => [
                    'one' => 'first',
                    'two' => 'second',
                ],
                'bags' => [
                    'first' => [1],
                    'second' => [1],
                ]
            ]
        ]);
        /** @var PrometheusManager $manager */
        $manager = resolve(PrometheusManager::class);

        $firstBag = $manager->defaultBag('one');
        $secondBag = $manager->defaultBag('two');

        $this->assertNotSame($firstBag, $secondBag);
    }

    public function testSetDefaultBag()
    {
        config([
            'prometheus' => [
                'defaults' => [
                    'one' => 'first',
                ],
                'bags' => [
                    'first' => [1],
                    'second' => [1],
                ]
            ]
        ]);
        /** @var PrometheusManager $manager */
        $manager = resolve(PrometheusManager::class);

        $firstBag = $manager->defaultBag('one');
        $manager->setDefaultBag('one', 'second');
        $secondBag = $manager->defaultBag('one');

        $this->assertNotSame($firstBag, $secondBag);
    }

    public function testSetContext()
    {
        config([
            'prometheus' => [
                'defaults' => [
                    'one' => 'first',
                    'two' => 'second',
                ],
                'bags' => [
                    'first' => [1],
                    'second' => [1],
                ]
            ]
        ]);
        /** @var PrometheusManager $manager */
        $manager = resolve(PrometheusManager::class);

        $manager->setCurrentContext('one');
        $firstBag = $manager->defaultBag();
        $manager->setCurrentContext('two');
        $secondBag = $manager->defaultBag();

        $this->assertNotSame($firstBag, $secondBag);
    }

    public function testInvokeDefaultBagsMethodOnCall()
    {
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
    }
}