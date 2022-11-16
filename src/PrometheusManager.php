<?php

namespace Madridianfox\LaravelPrometheus;

use InvalidArgumentException;

/**
 * @mixin MetricsBag
 */
class PrometheusManager
{
    private array $metricBags = [];
    private array $defaults = [];
    private string $currentContext = 'web';

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

    public function defaultBag(?string $context = null): MetricsBag
    {
        return $this->bag($this->defaultBagName($context));
    }

    public function setDefaultBag(string $context, string $bagName): void
    {
        $this->defaults[$context] = $bagName;
    }

    public function setCurrentContext(string $context): void
    {
        $this->currentContext = $context;
    }

    private function defaultBagName(?string $context = null): ?string
    {
        $defaults = array_merge(config('prometheus.defaults'), $this->defaults);

        return $defaults[$context ?? $this->currentContext];
    }

    private function createMetricsBag(string $bagName): MetricsBag
    {
        $config = config("prometheus.bags." . $bagName);
        if (!$config) {
            throw new InvalidArgumentException(
                "Metric bag with name '{$bagName}' is not defined"
            );
        }

        $metricsBag = new MetricsBag($config);
        foreach ($config['label_providers'] as $index => $value) {
            if (is_numeric($index)) {
                $metricsBag->addLabelProcessor(labelProcessorClass: $value);
            } else {
                $metricsBag->addLabelProcessor(labelProcessorClass: $index, parameters: $value);
            }
        }
        return $metricsBag;
    }

    public function __call($method, $parameters)
    {
        return $this->bag()->$method(...$parameters);
    }
}