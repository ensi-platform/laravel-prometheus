<?php

namespace Ensi\LaravelPrometheus\Metrics;

use Ensi\LaravelPrometheus\LabelMiddlewares\LabelMiddleware;
use Ensi\LaravelPrometheus\MetricsBag;

abstract class AbstractMetric
{
    protected MetricsBag $metricsBag;
    protected array $labels = [];
    protected string $help = "";
    /** @var array<LabelMiddleware> */
    protected array $middlewares = [];

    abstract public function update($value = 1, array $labelValues = []): void;

    public function labels(array $labels): self
    {
        $this->labels = $labels;

        return $this;
    }

    public function help(string $help): self
    {
        $this->help = $help;

        return $this;
    }

    public function middleware(...$middlewares): self
    {
        foreach ($middlewares as $middleware) {
            $this->middlewares[] = resolve($middleware);
        }

        return $this;
    }

    protected function enrichLabelNames(array $labels): array
    {
        foreach ($this->metricsBag->getMiddlewares() as $middleware) {
            $labels = array_merge($labels, $middleware->labels());
        }

        foreach ($this->middlewares as $middleware) {
            $labels = array_merge($labels, $middleware->labels());
        }

        return $labels;
    }

    protected function enrichLabelValues(array $labelValues): array
    {
        foreach ($this->metricsBag->getMiddlewares() as $middleware) {
            $labelValues = array_merge($labelValues, $middleware->values());
        }

        foreach ($this->middlewares as $middleware) {
            $labelValues = array_merge($labelValues, $middleware->values());
        }

        return $labelValues;
    }
}
