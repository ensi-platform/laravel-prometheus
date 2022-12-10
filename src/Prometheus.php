<?php

namespace Ensi\LaravelPrometheus;

use Ensi\LaravelPrometheus\Metrics\Counter;
use Ensi\LaravelPrometheus\Metrics\Gauge;
use Ensi\LaravelPrometheus\Metrics\Histogram;
use Ensi\LaravelPrometheus\Metrics\Summary;
use Illuminate\Support\Facades\Facade;

/**
 * @method static MetricsBag bag(?string $name = null)
 * @method static void setDefaultBag(string $bagName)
 * @method static void addMiddleware(string $labelProcessorClass, array $parameters = [])
 * @method static void addOnDemandMetric(string $onDemandMetricClass)
 *
 * @method static Counter counter(string $name, array $labels = [])
 * @method static Gauge gauge(string $name, array $labels = [])
 * @method static Histogram histogram(string $name, array $buckets, array $labels = [])
 * @method static Summary summary(string $name, int $maxAgeSeconds, array $quantiles, array $labels = [])
 * @method static void update(string $name, $value, array $labelValues)
 *
 * @method static void processOnDemandMetrics()
 * @method static string dumpTxt()
 * @method static void wipe()
 * @method static bool auth(\Illuminate\Http\Request $request)
 *
 * @see \Ensi\LaravelPrometheus\PrometheusManager
 */
class Prometheus extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'prometheus';
    }
}