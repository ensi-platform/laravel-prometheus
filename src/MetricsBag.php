<?php

namespace Ensi\LaravelPrometheus;

use Ensi\LaravelPrometheus\LabelMiddlewares\LabelMiddleware;
use Ensi\LaravelPrometheus\Metrics\AbstractMetric;
use Ensi\LaravelPrometheus\Metrics\Counter;
use Ensi\LaravelPrometheus\Metrics\Gauge;
use Ensi\LaravelPrometheus\Metrics\Histogram;
use Ensi\LaravelPrometheus\Metrics\Summary;
use Ensi\LaravelPrometheus\OnDemandMetrics\OnDemandMetric;
use Ensi\LaravelPrometheus\Storage\NullStorage;
use Ensi\LaravelPrometheus\Storage\Redis;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis as RedisManager;
use InvalidArgumentException;
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\Adapter;
use Prometheus\Storage\APC;
use Prometheus\Storage\APCng;
use Prometheus\Storage\InMemory;

class MetricsBag
{
    private ?CollectorRegistry $collectors = null;
    /** @var array<LabelMiddleware> */
    private array $middlewares = [];
    /** @var array<AbstractMetric> */
    private array $metrics = [];
    /** @var array<class-string> */
    private array $onDemandMetrics = [];

    public function __construct(private array $config)
    {
        foreach ($config['label_middlewares'] ?? [] as $index => $value) {
            if (is_numeric($index)) {
                $this->addMiddleware(labelProcessorClass: $value);
            } else {
                $this->addMiddleware(labelProcessorClass: $index, parameters: $value);
            }
        }

        foreach ($this->config['on_demand_metrics'] ?? [] as $onDemandMetricClass) {
            $this->addOnDemandMetric($onDemandMetricClass);
        }
    }

    public function getNamespace(): string
    {
        return $this->config['namespace'];
    }

    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }

    public function addMiddleware(string $labelProcessorClass, array $parameters = [])
    {
        $this->middlewares[] = resolve($labelProcessorClass, $parameters);
    }

    public function counter(string $name): Counter
    {
        if (!isset($this->metrics[$name])) {
            $this->metrics[$name] = new Counter($this, $name);
        }

        return $this->metrics[$name];
    }

    public function gauge(string $name): Gauge
    {
        if (!isset($this->metrics[$name])) {
            $this->metrics[$name] = new Gauge($this, $name);
        }

        return $this->metrics[$name];
    }

    public function histogram(string $name, array $buckets): Histogram
    {
        if (!isset($this->metrics[$name])) {
            $this->metrics[$name] = new Histogram($this, $name, $buckets);
        }

        return $this->metrics[$name];
    }

    public function summary(string $name, int $maxAgeSeconds, array $quantiles): Summary
    {
        if (!isset($this->metrics[$name])) {
            $this->metrics[$name] = new Summary($this, $name, $maxAgeSeconds, $quantiles);
        }

        return $this->metrics[$name];
    }

    private function isPrometheusEnabled(): bool
    {
        return config('prometheus.enabled');
    }

    public function update(string $name, $value, array $labelValues = []): void
    {
        if (!$this->isPrometheusEnabled()) {
            return;
        }

        $metric = $this->metrics[$name] ?? null;
        $metric?->update($value, $labelValues);
    }

    public function processOnDemandMetrics(): void
    {
        foreach ($this->onDemandMetrics as $onDemandMetric) {
            $onDemandMetric->update($this);
        }
    }

    public function addOnDemandMetric(string $onDemandMetricClass): void
    {
        /** @var OnDemandMetric $onDemandMetric */
        $onDemandMetric = resolve($onDemandMetricClass);
        $onDemandMetric->register($this);
        $this->onDemandMetrics[$onDemandMetricClass] = $onDemandMetric;
    }

    public function dumpTxt(): string
    {
        if (!$this->isPrometheusEnabled()) {
            return '';
        }

        $renderer = new RenderTextFormat();
        try {
            return $renderer->render($this->getCollectors()->getMetricFamilySamples());
        } catch (\RedisException) {
        }

        return "";
    }

    public function getCollectors(): CollectorRegistry
    {
        if (!$this->collectors) {
            $this->collectors = new CollectorRegistry($this->getStorage(), false);
        }

        return $this->collectors;
    }

    private function getStorage(): Adapter
    {
        switch (true) {
            case array_key_exists('connection', $this->config):
                return $this->createStorageFromConnection($this->config['connection']);
            case array_key_exists('redis', $this->config):
                return new Redis($this->config['redis']);
            case array_key_exists('apcu', $this->config):
                return new APC($this->config['apcu']['prefix']);
            case array_key_exists('apcu-ng', $this->config):
                return new APCng($this->config['apcu-ng']['prefix']);
            case array_key_exists('memory', $this->config):
                return new InMemory();
            case array_key_exists('null-storage', $this->config):
                return new NullStorage();
        }
        throw new InvalidArgumentException("Missing storage configuration");
    }

    private function createStorageFromConnection(array $options): Adapter
    {
        try {
            $redisConnection = RedisManager::connection($options['connection']);

            return Redis::fromExistingConnection($redisConnection->client(), [
                'bag' => $options['bag'],
            ]);
        }  catch (\RedisException) {
            return new NullStorage();
        }
    }

    public function wipe(): void
    {
        if (!$this->isPrometheusEnabled()) {
            return;
        }

        $this->getCollectors()->wipeStorage();
    }

    public function auth(Request $request): bool
    {
        $authEnabled = isset($this->config['basic_auth']);
        if (!$authEnabled) {
            return true;
        }

        return $this->config['basic_auth']['login'] == $request->getUser()
            && $this->config['basic_auth']['password'] == $request->getPassword();
    }
}