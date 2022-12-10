<?php

namespace Ensi\LaravelPrometheus\OnDemandMetrics;

use Ensi\LaravelPrometheus\MetricsBag;

interface OnDemandMetric
{
    public function register(MetricsBag $metricsBag): void;
    public function update(MetricsBag $metricsBag): void;
}