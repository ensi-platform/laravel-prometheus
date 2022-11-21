<?php

namespace Madridianfox\LaravelPrometheus;

use Illuminate\Support\Facades\Facade;

/**
 * @method static MetricsBag bag(?string $name = null)
 * @method static void setDefaultBag(string $bagName)
 *
 * @method static void addLabelProcessor(string $labelProcessorClass, array $parameters = [])
 * @method static void declareCounter(string $name, array $labels = [])
 * @method static void declareGauge(string $name, array $labels = [])
 * @method static void declareHistogram(string $name, array $buckets, array $labels = [])
 * @method static void declareSummary(string $name, int $maxAgeSeconds, array $quantiles, array $labels = [])
 * @method static void updateCounter(string $name, array $labelValues, $value = 1)
 * @method static void updateGauge(string $name, array $labelValues, $value = 1)
 * @method static void updateHistogram(string $name, array $labelValues, $value = 1)
 * @method static void updateSummary(string $name, array $labelValues, $value = 1)
 * @method static string dumpTxt()
 * @method static bool auth(\Illuminate\Http\Request $request)
 *
 * @see \Madridianfox\LaravelPrometheus\PrometheusManager
 */
class Prometheus extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'prometheus';
    }
}