<?php

namespace Madridianfox\LaravelPrometheus\OnDemandMetrics;

use Madridianfox\LaravelPrometheus\MetricsBag;

interface OnDemandMetric
{
    public function register(MetricsBag $metricsBag): void;
    public function update(MetricsBag $metricsBag): void;
}