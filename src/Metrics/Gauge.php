<?php

namespace Ensi\LaravelPrometheus\Metrics;

use Ensi\LaravelPrometheus\MetricsBag;
use Prometheus\Gauge as LowLevelGauge;

class Gauge extends AbstractMetric
{
    private ?LowLevelGauge $gauge = null;

    public function __construct(
        protected MetricsBag $metricsBag,
        private string $name
    ) {
    }

    public function update($value = 1, array $labelValues = []): void
    {
        try {
            $this->getGauge()->set(
                $value,
                $this->enrichLabelValues($labelValues)
            );
        } catch (\RedisException $e) {
        }
    }

    private function getGauge(): LowLevelGauge
    {
        if (!$this->gauge) {
            try {
                $this->gauge = $this->metricsBag->getCollectors()->registerGauge(
                    $this->metricsBag->getNamespace(),
                    $this->name,
                    $this->help,
                    $this->enrichLabelNames($this->labels),
                );
            } catch (\RedisException) {
            }
        }

        return $this->gauge;
    }
}