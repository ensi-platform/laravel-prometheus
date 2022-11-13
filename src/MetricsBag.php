<?php

namespace Madridianfox\LaravelPrometheus;

use Prometheus\CollectorRegistry;
use Prometheus\Counter;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\Adapter;
use Prometheus\Storage\APC;
use Prometheus\Storage\APCng;
use Prometheus\Storage\InMemory;
use Prometheus\Storage\Redis;

class MetricsBag
{
    private ?CollectorRegistry $collectors = null;
    /** @var array<LabelProvider> */
    private array $labelProcessors = [];
    private array $collectorDeclarations = [];

    public function __construct(private array $config)
    {
    }

    public function addLabelProcessor(string $labelProcessorClass, array $parameters = [])
    {
        $this->labelProcessors[] = resolve($labelProcessorClass, $parameters);
    }

    public function declareCounter(string $name, array $labels = []): void
    {
        $this->collectorDeclarations[$name] = [
            'labels' => $labels,
            'created' => false,
        ];
    }

    private function getCounter(string $name): Counter
    {
        if (!array_key_exists($name, $this->collectorDeclarations)) {
            throw new \InvalidArgumentException('Undefined metric ' . $name);
        }

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

    public function updateCounter(string $name, array $labelValues, $value = 1): void
    {
        $this->getCounter($name)->incBy(
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
        foreach ($this->labelProcessors as $labelProcessor) {
            foreach ($labelProcessor->labels() as $additionalLabel) {
                $labels[] = $additionalLabel;
            }
        }
        logger()->debug('labels', $labels);
        return $labels;
    }

    private function enrichLabelValues(array $labelValues): array
    {
        foreach ($this->labelProcessors as $labelProcessor) {
            foreach ($labelProcessor->values() as $additionalValue) {
                $labelValues[] = $additionalValue;
            }
        }

        logger()->debug('values', $labelValues);
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
        return match($this->config['storage']) {
            'redis' => new Redis($this->config['redis']),
            'apcu' => new APC($this->config['apcu_prefix']),
            'apcu_ng' => new APCng($this->config['apcu_prefix']),
            'memory' => new InMemory(),
        };
    }

    public function wipe(): void
    {
        $this->getCollectors()->wipeStorage();
    }
}