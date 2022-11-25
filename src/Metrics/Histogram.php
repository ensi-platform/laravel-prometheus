<?php

namespace Madridianfox\LaravelPrometheus\Metrics;

use Madridianfox\LaravelPrometheus\MetricsBag;
use Prometheus\Histogram as LowLevelHistogram;

class Histogram extends AbstractMetric
{
    private ?LowLevelHistogram $histogram = null;

    public function __construct(
        protected MetricsBag $metricsBag,
        private string $name,
        private array $buckets,
    ) {
    }

    public function update($value = 1, array $labelValues = []): void
    {
        $this->getHistogram()->observe(
            $value,
            $this->enrichLabelValues($labelValues)
        );
    }

    private function getHistogram(): LowLevelHistogram
    {
        if (!$this->histogram) {
            $this->histogram = $this->metricsBag->getCollectors()->registerHistogram(
                $this->metricsBag->getNamespace(),
                $this->name,
                $this->help,
                $this->enrichLabelNames($this->labels),
                $this->buckets,
            );
        }

        return $this->histogram;
    }
}