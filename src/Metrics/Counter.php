<?php

namespace Ensi\LaravelPrometheus\Metrics;

use Ensi\LaravelPrometheus\MetricsBag;
use Prometheus\Counter as LowLevelCounter;

class Counter extends AbstractMetric
{
    private ?LowLevelCounter $counter = null;

    public function __construct(
        protected MetricsBag $metricsBag,
        private string $name
    ) {
    }

    public function update($value = 1, array $labelValues = []): void
    {
        try {
            $this->getCounter()->incBy(
                $value,
                $this->enrichLabelValues($labelValues)
            );
        } catch (\RedisException $e) {
        }
    }

    private function getCounter(): LowLevelCounter
    {
        if (!$this->counter) {
            try {
                $this->counter = $this->metricsBag->getCollectors()->registerCounter(
                    $this->metricsBag->getNamespace(),
                    $this->name,
                    $this->help,
                    $this->enrichLabelNames($this->labels),
                );
            } catch (\RedisException) {
            }
        }

        return $this->counter;
    }
}