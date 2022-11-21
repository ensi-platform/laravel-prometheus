<?php

namespace Madridianfox\LaravelPrometheus\OnDemandMetrics;

use Madridianfox\LaravelPrometheus\MetricsBag;

class MemoryUsageOnDemandMetric extends OnDemandMetric
{
    public function register(MetricsBag $metricsBag): void
    {
        $metricsBag->declareGauge('memory_usage_bytes');
    }

    public function update(MetricsBag $metricsBag): void
    {
        $metricsBag->updateGauge('memory_usage_bytes', [], memory_get_peak_usage(true));
    }
}