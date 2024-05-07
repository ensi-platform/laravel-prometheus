<?php

namespace Ensi\LaravelPrometheus\Metrics;

use Ensi\LaravelPrometheus\MetricsBag;
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
        try {
            $this->getHistogram()->observe(
                $value,
                $this->enrichLabelValues($labelValues)
            );
        } catch (\RedisException $e) {
        }
    }

    private function getHistogram(): LowLevelHistogram
    {
        if (!$this->histogram) {
            try {
                $this->histogram = $this->metricsBag->getCollectors()->registerHistogram(
                    $this->metricsBag->getNamespace(),
                    $this->name,
                    $this->help,
                    $this->enrichLabelNames($this->labels),
                    $this->buckets,
                );
            } catch (\RedisException) {
            }
        }

        return $this->histogram;
    }
}
