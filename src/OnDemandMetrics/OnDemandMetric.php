<?php

namespace Madridianfox\LaravelPrometheus\OnDemandMetrics;

use Madridianfox\LaravelPrometheus\MetricsBag;

abstract class OnDemandMetric
{
    public abstract function register(MetricsBag $metricsBag): void;
    public abstract function update(MetricsBag $metricsBag): void;
}