<?php

namespace Madridianfox\LaravelPrometheus;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis as RedisManager;
use InvalidArgumentException;
use Madridianfox\LaravelPrometheus\LabelMiddlewares\LabelMiddleware;
use Madridianfox\LaravelPrometheus\Storage\Redis;
use Prometheus\CollectorRegistry;
use Prometheus\Counter;
use Prometheus\Gauge;
use Prometheus\Histogram;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\Adapter;
use Prometheus\Storage\APC;
use Prometheus\Storage\APCng;
use Prometheus\Storage\InMemory;
use Prometheus\Summary;

class MetricsBag
{
    private ?CollectorRegistry $collectors = null;
    /** @var array<LabelMiddleware> */
    private array $labelMiddlewares = [];
    private array $collectorDeclarations = [];

    public function __construct(private array $config)
    {
        foreach ($config['label_middlewares'] ?? [] as $index => $value) {
            if (is_numeric($index)) {
                $this->addLabelMiddleware(labelProcessorClass: $value);
            } else {
                $this->addLabelMiddleware(labelProcessorClass: $index, parameters: $value);
            }
        }
    }

    public function addLabelMiddleware(string $labelProcessorClass, array $parameters = [])
    {
        $this->labelMiddlewares[] = resolve($labelProcessorClass, $parameters);
    }

    public function declareCounter(string $name, array $labels = []): void
    {
        $this->collectorDeclarations[$name] = [
            'labels' => $labels,
            'created' => false,
        ];
    }

    public function declareGauge(string $name, array $labels = []): void
    {
        $this->collectorDeclarations[$name] = [
            'labels' => $labels,
            'created' => false,
        ];
    }

    public function declareHistogram(string $name, array $buckets, array $labels = []): void
    {
        $this->collectorDeclarations[$name] = [
            'labels' => $labels,
            'buckets' => $buckets,
            'created' => false,
        ];
    }

    public function declareSummary(string $name, int $maxAgeSeconds, array $quantiles, array $labels = []): void
    {
        $this->collectorDeclarations[$name] = [
            'labels' => $labels,
            'max_age_seconds' => $maxAgeSeconds,
            'quantiles' => $quantiles,
            'created' => false,
        ];
    }

    private function checkMetricDeclared(string $name): void
    {
        if (!array_key_exists($name, $this->collectorDeclarations)) {
            throw new InvalidArgumentException('Undefined metric ' . $name);
        }
    }

    private function getCounter(string $name): Counter
    {
        $this->checkMetricDeclared($name);

        if (!$this->collectorDeclarations[$name]['created']) {
            $this->getCollectors()->registerCounter(
                $this->config['namespace'],
                $name,
                "",
                $this->enrichLabelNames($this->collectorDeclarations[$name]['labels']),
            );
            $this->collectorDeclarations[$name]['created'] = true;
        }

        return $this->getCollectors()->getCounter(
            $this->config['namespace'],
            $name,
        );
    }

    private function getGauge(string $name): Gauge
    {
        $this->checkMetricDeclared($name);

        if (!$this->collectorDeclarations[$name]['created']) {
            $this->getCollectors()->registerGauge(
                $this->config['namespace'],
                $name,
                "",
                $this->enrichLabelNames($this->collectorDeclarations[$name]['labels']),
            );
            $this->collectorDeclarations[$name]['created'] = true;
        }

        return $this->getCollectors()->getGauge(
            $this->config['namespace'],
            $name,
        );
    }

    private function getHistogram($name): Histogram
    {
        $this->checkMetricDeclared($name);

        if (!$this->collectorDeclarations[$name]['created']) {
            $this->getCollectors()->registerHistogram(
                $this->config['namespace'],
                $name,
                "",
                $this->enrichLabelNames($this->collectorDeclarations[$name]['labels']),
                $this->collectorDeclarations[$name]['buckets']
            );
            $this->collectorDeclarations[$name]['created'] = true;
        }

        return $this->getCollectors()->getHistogram(
            $this->config['namespace'],
            $name,
        );
    }

    private function getSummary(string $name): Summary
    {
        $this->checkMetricDeclared($name);

        if (!$this->collectorDeclarations[$name]['created']) {
            $this->getCollectors()->registerSummary(
                $this->config['namespace'],
                $name,
                "",
                $this->enrichLabelNames($this->collectorDeclarations[$name]['labels']),
                $this->collectorDeclarations[$name]['max_age_seconds'],
                $this->collectorDeclarations[$name]['quantiles'],
            );
            $this->collectorDeclarations[$name]['created'] = true;
        }

        return $this->getCollectors()->getSummary(
            $this->config['namespace'],
            $name,
        );
    }

    public function updateCounter(string $name, array $labelValues, $value = 1): void
    {
        $this->getCounter($name)->incBy(
            $value,
            $this->enrichLabelValues($labelValues)
        );
    }

    public function updateGauge(string $name, array $labelValues, $value = 1): void
    {
        $this->getGauge($name)->set(
            $value,
            $this->enrichLabelValues($labelValues)
        );
    }

    public function updateHistogram(string $name, array $labelValues, $value = 1): void
    {
        $this->getHistogram($name)->observe(
            $value,
            $this->enrichLabelValues($labelValues)
        );
    }

    public function updateSummary(string $name, array $labelValues, $value = 1): void
    {
        $this->getSummary($name)->observe(
            $value,
            $this->enrichLabelValues($labelValues)
        );
    }

    public function dumpTxt(): string
    {
        $renderer = new RenderTextFormat();
        return $renderer->render($this->getCollectors()->getMetricFamilySamples());
    }

    private function enrichLabelNames(array $labels): array
    {
        foreach ($this->labelMiddlewares as $labelProcessor) {
            foreach ($labelProcessor->labels() as $additionalLabel) {
                $labels[] = $additionalLabel;
            }
        }

        return $labels;
    }

    private function enrichLabelValues(array $labelValues): array
    {
        foreach ($this->labelMiddlewares as $labelProcessor) {
            foreach ($labelProcessor->values() as $additionalValue) {
                $labelValues[] = $additionalValue;
            }
        }

        return $labelValues;
    }

    private function getCollectors(): CollectorRegistry
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
        }
        throw new InvalidArgumentException("Missing storage configuration");
    }

    private function createStorageFromConnection(array $options): Adapter
    {
        $redisConnection = RedisManager::connection($options['connection']);

        return Redis::fromExistingConnection($redisConnection->client(), [
            'bag' => $options['bag'],
        ]);
    }

    public function wipe(): void
    {
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