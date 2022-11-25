<?php

namespace Madridianfox\LaravelPrometheus\Tests\Fixstures;

use Madridianfox\LaravelPrometheus\MetricsBag;
use Madridianfox\LaravelPrometheus\OnDemandMetrics\OnDemandMetric;

class SomeOnDemandMetric implements OnDemandMetric
{
    public function register(MetricsBag $metricsBag): void
    {
        $metricsBag->counter('on_demand_counter');
    }

    public function update(MetricsBag $metricsBag): void
    {
        $metricsBag->update('on_demand_counter', 1);
    }
}