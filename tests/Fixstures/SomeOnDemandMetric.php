<?php

namespace Ensi\LaravelPrometheus\Tests\Fixstures;

use Ensi\LaravelPrometheus\MetricsBag;
use Ensi\LaravelPrometheus\OnDemandMetrics\OnDemandMetric;

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