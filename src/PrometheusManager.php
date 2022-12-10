<?php

namespace Ensi\LaravelPrometheus;

use InvalidArgumentException;

/**
 * @mixin MetricsBag
 */
class PrometheusManager
{
    private array $metricBags = [];
    private ?string $defaultBagName = null;

    public function bag(?string $name = null): MetricsBag
    {
        $bagName = $name ?? $this->defaultBagName();

        if (is_null($bagName)) {
            throw new InvalidArgumentException(
                "Attempt to get a metrics bag with name NULL"
            );
        }

        if (!array_key_exists($bagName, $this->metricBags)) {
            $this->metricBags[$bagName] = $this->createMetricsBag($bagName);
        }

        return $this->metricBags[$bagName];
    }

    public function setDefaultBag(string $bagName): void
    {
        $this->defaultBagName = $bagName;
    }

    private function defaultBagName(): ?string
    {
        return $this->defaultBagName ??= config('prometheus.default_bag');
    }

    protected function createMetricsBag(string $bagName): MetricsBag
    {
        $config = config("prometheus.bags." . $bagName);
        if (!$config) {
            throw new InvalidArgumentException(
                "Metric bag with name '{$bagName}' is not defined"
            );
        }

        return new MetricsBag($config);
    }

    public function __call($method, $parameters)
    {
        return $this->bag()->$method(...$parameters);
    }
}