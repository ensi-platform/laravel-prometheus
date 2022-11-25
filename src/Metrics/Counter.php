<?php

namespace Madridianfox\LaravelPrometheus\Metrics;

use Madridianfox\LaravelPrometheus\MetricsBag;
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
        $this->getCounter()->incBy(
            $value,
            $this->enrichLabelValues($labelValues)
        );
    }

    private function getCounter(): LowLevelCounter
    {
        if (!$this->counter) {
            $this->counter = $this->metricsBag->getCollectors()->registerCounter(
                $this->metricsBag->getNamespace(),
                $this->name,
                $this->help,
                $this->enrichLabelNames($this->labels),
            );
        }

        return $this->counter;
    }
}