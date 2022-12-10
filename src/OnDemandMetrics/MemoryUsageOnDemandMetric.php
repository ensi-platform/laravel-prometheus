<?php

namespace Ensi\LaravelPrometheus\OnDemandMetrics;

use Ensi\LaravelPrometheus\MetricsBag;

class MemoryUsageOnDemandMetric implements OnDemandMetric
{
    public function register(MetricsBag $metricsBag): void
    {
        $metricsBag->gauge('memory_usage_bytes');
    }

    public function update(MetricsBag $metricsBag): void
    {
        $metricsBag->update('memory_usage_bytes', memory_get_peak_usage(true));
    }
}