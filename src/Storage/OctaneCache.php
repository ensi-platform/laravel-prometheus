<?php

declare(strict_types=1);

namespace Ensi\LaravelPrometheus\Storage;

use Illuminate\Support\Arr;
use Laravel\Octane\Facades\Octane;
use Prometheus\Exception\StorageException;
use Prometheus\Math;
use Prometheus\MetricFamilySamples;
use Prometheus\Storage\Adapter;
use RuntimeException;
use Swoole\Table;

class OctaneCache implements Adapter
{
    public const PROMETHEUS_PREFIX = 'PROMETHEUS_';

    private Table $gauges;
    private Table $gaugeValues;

    private Table $сounters;
    private Table $сounterValues;

    private Table $summaries;
    private Table $summaryValues;

    private Table $histograms;
    private Table $histogramValues;

    /**
     * Redis constructor.
     * @param mixed[] $options
     */
    public function __construct(private string $prometheusPrefix = self::PROMETHEUS_PREFIX)
    {
        $this->gauges = Octane::table('gauges');
        $this->gaugeValues = Octane::table('gauge_values');

        $this->сounters = Octane::table('сounters');
        $this->сounterValues = Octane::table('сounter_values');

        $this->summaries = Octane::table('summaries');
        $this->summaryValues = Octane::table('summary_values');

        $this->histograms = Octane::table('histograms');
        $this->histogramValues = Octane::table('histogram_values');
    }

    /**
     * @return MetricFamilySamples[]
     * @throws StorageException
     */
    public function collect(): array
    {
        $metrics = $this->collectHistograms();
        $metrics = array_merge($metrics, $this->collectGauges());
        $metrics = array_merge($metrics, $this->collectCounters());
        $metrics = array_merge($metrics, $this->collectSummaries());

        return $metrics;
    }

    /**
     * @param mixed[] $data
     * @throws StorageException
     */
    public function updateHistogram(array $data): void
    {
        // Initialize the sum
        $metaKey = $this->metaKey($data);
        $metaKeyHash = hash('md5', $metaKey);
        $metaKeyValue = $this->histograms->get($metaKeyHash);

        if (!$metaKeyValue) {
            $metaKeyValue = [
                'meta' => $this->metaData($data),
                'valueKeys' => '',
            ];
        }

        $sumKey = $this->histogramBucketValueKey($data, 'sum');
        $sumKeyHash = hash('md5', $sumKey);
        $sumValue = $this->histogramValues->get($sumKeyHash);
        if (!$sumValue) {
            $metaKeyValue['valueKeys'] = $this->implodeKeysString($metaKeyValue['valueKeys'], $sumKeyHash);
            $sumValue = [
                'value' => 0,
                'key' => $sumKey
            ];
        } 
        $sumValue['value'] += $data['value'];
        $this->histogramValues->set($sumKeyHash, $sumValue);



        $bucketToIncrease = '+Inf';
        foreach ($data['buckets'] as $bucket) {
            if ($data['value'] <= $bucket) {
                $bucketToIncrease = $bucket;

                break;
            }
        }

        $bucketKey = $this->histogramBucketValueKey($data, $bucketToIncrease);
        $bucketKeyHash = hash('md5', $bucketKey);
        $bucketValue = $this->histogramValues->get($bucketKeyHash);
        if (!$bucketValue) {
            $metaKeyValue['valueKeys'] = $this->implodeKeysString($metaKeyValue['valueKeys'], $bucketKeyHash);
            $bucketValue = [
                'value' => 0,
                'key' => $bucketKey
            ];
        }
        $bucketValue['value'] += 1;
        $this->histogramValues->set($bucketKeyHash, $bucketValue);

        $this->summaries->set($metaKeyHash, $metaKeyValue);
    }

    /**
     * @param mixed[] $data
     * @throws StorageException
     */
    public function updateSummary(array $data): void
    {
        $metaKey = $this->metaKey($data);
        $metaKeyHash = hash('md5', $metaKey);
        $valueKey = $this->valueKey($data);
        $valueKeyHash = hash('md5', $valueKey);

        $metaKeyValue = $this->gauges->get($metaKeyHash);
        if (!$metaKeyValue) {
            $metaKeyValue = [
                'meta' => $this->metaData($data),
                'valueKeys' => '',
            ];
        }

        $summaryValue = $this->summaryValues->get($valueKeyHash);
        if (!$summaryValue) {
            $metaKeyValue['valueKeys'] = $this->implodeKeysString($metaKeyValue['valueKeys'], $valueKeyHash);
            $summaryValue = [
                'key' => $valueKey,
                'sampleTimes' => '',
                'sampleValues' => '',
            ];
        }

        $summaryValue['sampleTimes'] = $this->implodeKeysString($summaryValue['sampleTimes'], (string) time());
        $summaryValue['sampleValues'] = $this->implodeKeysString($summaryValue['sampleValues'], (string) $data['value']);

        $this->summaryValues->set($valueKeyHash, $summaryValue);
        $this->summaries->set($metaKeyHash, $metaKeyValue);
    }

    /**
     * @param mixed[] $data
     * @throws StorageException
     */
    public function updateGauge(array $data): void
    {
        $metaKey = $this->metaKey($data);
        $metaKeyHash = hash('md5', $metaKey);
        $valueKey = $this->valueKey($data);
        $valueKeyHash = hash('md5', $valueKey);

        $metaKeyValue = $this->gauges->get($metaKeyHash);
        if (!$metaKeyValue) {
            $metaKeyValue = [
                'meta' => $this->metaData($data),
                'valueKeys' => '',
            ];
        }

        $gaugeValue = $this->gaugeValues->get($valueKey);
        if (!$gaugeValue) {
            $metaKeyValue['valueKeys'] = $this->implodeKeysString($metaKeyValue['valueKeys'], $valueKeyHash);
            $gaugeValue = [
                'value' => 0,
                'key' => $valueKey
            ];
        }
        if ($data['command'] === Adapter::COMMAND_SET) {
            $gaugeValue['value'] = $data['value'];
        } else {
            $gaugeValue['value'] += $data['value'];
        }
        
        $this->gaugeValues->set($valueKeyHash, $gaugeValue);
        $this->gauges->set($metaKeyHash, $metaKeyValue);
    }

    /**
     * @param mixed[] $data
     * @throws StorageException
     */
    public function updateCounter(array $data): void
    {
        $metaKey = $this->metaKey($data);
        $metaKeyHash = hash('md5', $metaKey);
        $valueKey = $this->valueKey($data);
        $valueKeyHash = hash('md5', $valueKey);

        $metaKeyValue = $this->сounters->get($metaKeyHash);
        if (!$metaKeyValue) {
            $metaKeyValue = [
                'meta' => $this->metaData($data),
                'valueKeys' => '',
            ];
        }
        $сounterValue = $this->сounterValues->get($valueKeyHash);
        if (!$сounterValue) {
            $metaKeyValue['valueKeys'] = $this->implodeKeysString($metaKeyValue['valueKeys'], $valueKeyHash);
            $сounterValue = [
                'value' => 0,
                'key' => $valueKey
            ];
        }
        if ($data['command'] === Adapter::COMMAND_SET) {
            $сounterValue['value'] = 0;
        } else {
            $сounterValue['value'] += $data['value'];
        }

        $this->сounterValues->set($valueKeyHash, $сounterValue);
        $this->сounters->set($metaKeyHash, $metaKeyValue);
    }

    /**
     * @return MetricFamilySamples[]
     */
    private function collectHistograms(): array
    {
        $histograms = [];
        foreach ($this->histograms as $histogram) {
            $metaData = json_decode($histogram['meta'], true);
            $data = [
                'name' => $metaData['name'],
                'help' => $metaData['help'],
                'type' => $metaData['type'],
                'labelNames' => $metaData['labelNames'],
                'buckets' => $metaData['buckets'],
            ];

            // Add the Inf bucket so we can compute it later on
            $data['buckets'][] = '+Inf';

            $histogramBuckets = [];
            foreach (explode('::', $histogram['valueKeys']) as $valueKey) {
                $value = $this->histogramValues->get($valueKey);
                $parts = explode(':', $value['key']);
                $labelValues = $parts[2];
                $bucket = $parts[3];
                // Key by labelValues
                $histogramBuckets[$labelValues][$bucket] = $value;
            }

            // Compute all buckets
            $labels = array_keys($histogramBuckets);
            sort($labels);
            foreach ($labels as $labelValues) {
                $acc = 0;
                $decodedLabelValues = $this->decodeLabelValues($labelValues);
                foreach ($data['buckets'] as $bucket) {
                    $bucket = (string)$bucket;
                    if (!isset($histogramBuckets[$labelValues][$bucket])) {
                        $data['samples'][] = [
                            'name' => $metaData['name'] . '_bucket',
                            'labelNames' => ['le'],
                            'labelValues' => array_merge($decodedLabelValues, [$bucket]),
                            'value' => $acc,
                        ];
                    } else {
                        $acc += $histogramBuckets[$labelValues][$bucket];
                        $data['samples'][] = [
                            'name' => $metaData['name'] . '_' . 'bucket',
                            'labelNames' => ['le'],
                            'labelValues' => array_merge($decodedLabelValues, [$bucket]),
                            'value' => $acc,
                        ];
                    }
                }

                // Add the count
                $data['samples'][] = [
                    'name' => $metaData['name'] . '_count',
                    'labelNames' => [],
                    'labelValues' => $decodedLabelValues,
                    'value' => $acc,
                ];

                // Add the sum
                $data['samples'][] = [
                    'name' => $metaData['name'] . '_sum',
                    'labelNames' => [],
                    'labelValues' => $decodedLabelValues,
                    'value' => $histogramBuckets[$labelValues]['sum'],
                ];
            }
            $histograms[] = new MetricFamilySamples($data);
        }

        return $histograms;
    }

    /**
     * @return MetricFamilySamples[]
     */
    private function collectSummaries(): array
    {
        $math = new Math();
        $summaries = [];
        foreach ($this->summaries as $metaKey => $summary) {
            $metaData = json_decode($summary['meta'], true);
            $data = [
                'name' => $metaData['name'],
                'help' => $metaData['help'],
                'type' => $metaData['type'],
                'labelNames' => $metaData['labelNames'],
                'maxAgeSeconds' => $metaData['maxAgeSeconds'],
                'quantiles' => $metaData['quantiles'],
                'samples' => [],
            ];

            foreach (explode('::', $summary['valueKeys']) as $valueKey) {

                $summaryValue = $this->summaryValues->get($valueKey);
                $parts = explode(':', $summaryValue['key']);
                $labelValues = $parts[2];
                $decodedLabelValues = $this->decodeLabelValues($labelValues);

                $sampleTimes = explode('::', $summaryValue['sampleTimes']);
                $values = Arr::mapWithKeys(
                    explode('::', $summaryValue['sampleValues']),
                    fn ($sampleValue, $key) => ['value' => (float) $sampleValue, 'time' => (int) $sampleTimes[$key]]
                );

                // Remove old data
                $values = array_filter($values, function (array $value) use ($data): bool {
                    return time() - $value['time'] <= $data['maxAgeSeconds'];
                });
                if (count($values) === 0) {
                    continue;
                    $this->summaryValues->del($valueKey);
                }

                // Compute quantiles
                usort($values, function (array $value1, array $value2) {
                    if ($value1['value'] === $value2['value']) {
                        return 0;
                    }

                    return ($value1['value'] < $value2['value']) ? -1 : 1;
                });

                foreach ($data['quantiles'] as $quantile) {
                    $data['samples'][] = [
                        'name' => $metaData['name'],
                        'labelNames' => ['quantile'],
                        'labelValues' => array_merge($decodedLabelValues, [$quantile]),
                        'value' => $math->quantile(array_column($values, 'value'), $quantile),
                    ];
                }

                // Add the count
                $data['samples'][] = [
                    'name' => $metaData['name'] . '_count',
                    'labelNames' => [],
                    'labelValues' => $decodedLabelValues,
                    'value' => count($values),
                ];

                // Add the sum
                $data['samples'][] = [
                    'name' => $metaData['name'] . '_sum',
                    'labelNames' => [],
                    'labelValues' => $decodedLabelValues,
                    'value' => array_sum(array_column($values, 'value')),
                ];
            }

            if (count($data['samples']) > 0) {
                $summaries[] = new MetricFamilySamples($data);
            } else {
                $this->summaries->del($metaKey);
            }
        }

        return $summaries;
    }

    /**
     * @return MetricFamilySamples[]
     */
    private function collectGauges(): array
    {
        $result = [];
        foreach ($this->gauges as $key => $metric) {
            $metaData = json_decode($metric['meta'], true);
            $data = [
                'name' => $metaData['name'],
                'help' => $metaData['help'],
                'type' => $metaData['type'],
                'labelNames' => $metaData['labelNames'],
                'samples' => [],
            ];
            foreach (explode('::', $metric['valueKeys']) as $valueKey) {
                $value = $this->gaugeValues->get($valueKey, 'value');
                $parts = explode(':', $value['key']);
                $labelValues = $parts[2];
                $data['samples'][] = [
                    'name' => $metaData['name'],
                    'labelNames' => [],
                    'labelValues' => $this->decodeLabelValues($labelValues),
                    'value' => $value,
                ];

                $this->gaugeValues->del($valueKey);
            }

            $result[] = new MetricFamilySamples($data);

            $this->сounters->del($key);
        }

        return $result;
    }

    /**
     * @return MetricFamilySamples[]
     */
    private function collectCounters(): array
    {
        $result = [];
        foreach ($this->сounters as $key => $metric) {
            $metaData = json_decode($metric['meta'], true);
            $data = [
                'name' => $metaData['name'],
                'help' => $metaData['help'],
                'type' => $metaData['type'],
                'labelNames' => $metaData['labelNames'],
                'samples' => [],
            ];
            foreach (explode('::', $metric['valueKeys']) as $valueKey) {
                $value = $this->сounterValues->get($valueKey, 'value');
                $parts = explode(':', $value['key']);
                $labelValues = $parts[2];
                $data['samples'][] = [
                    'name' => $metaData['name'],
                    'labelNames' => [],
                    'labelValues' => $this->decodeLabelValues($labelValues),
                    'value' => $value,
                ];

                $this->сounterValues->del($valueKey);
            }

            $result[] = new MetricFamilySamples($data);

            $this->сounters->del($key);
        }

        return $result;
    }

    /**
     * Removes all previously stored data from apcu
     *
     * @return void
     */
    public function wipeStorage(): void
    {
        $this->clearTable($this->gauges);
        $this->clearTable($this->gaugeValues);

        $this->clearTable($this->сounters);
        $this->clearTable($this->сounterValues);

        $this->clearTable($this->summaries);
        $this->clearTable($this->summaryValues);

        $this->clearTable($this->histograms);
        $this->clearTable($this->histogramValues);
    }

    /**
     * @param Table $table
     * @return void
     */
    private function clearTable(Table $table): void
    {
        $table->rewind();
        while ($table->valid()) {
            $table->del($table->key());
            $table->next();
        }
    }

    /**
     * @param mixed[] $data
     * @return string
     */
    private function valueKey(array $data): string
    {
        return implode(':', [
            $this->prometheusPrefix,
            $data['type'],
            $data['name'],
            $this->encodeLabelValues($data['labelValues']),
            'value',
        ]);
    }

    /**
     * @param mixed[] $data
     * @return string
     */
    private function implodeKeysString(string $keys, string $key): string
    {
        return implode('::', [
            $keys,
            $key,
        ]);
    }

    /**
    * @param mixed[] $data
    *
    * @return string
    */
    protected function metaKey(array $data): string
    {
        return implode(':', [
            $this->prometheusPrefix,
            $data['type'],
            $data['name'],
            'meta',
        ]);
    }

    /**
     * @param mixed[] $data
     * @return mixed[]
     */
    private function metaData(array $data): string
    {
        $metricsMetaData = $data;
        unset($metricsMetaData['value'], $metricsMetaData['command'], $metricsMetaData['labelValues']);

        return json_encode($metricsMetaData);
    }

    /**
     * @param mixed[] $values
     * @return string
     * @throws RuntimeException
     */
    private function encodeLabelValues(array $values): string
    {
        $json = json_encode($values);
        if (false === $json) {
            throw new RuntimeException(json_last_error_msg());
        }

        return base64_encode($json);
    }

    /**
    * @param string $values
    * @return mixed[]
    * @throws RuntimeException
    */
    private function decodeLabelValues(string $values): array
    {
        $json = base64_decode($values, true);
        if (false === $json) {
            throw new RuntimeException('Cannot base64 decode label values');
        }
        $decodedValues = json_decode($json, true);
        if (false === $decodedValues) {
            throw new RuntimeException(json_last_error_msg());
        }

        return $decodedValues;
    }

    /**
     * @param mixed[]    $data
     * @param string|int $bucket
     *
     * @return string
     */
    protected function histogramBucketValueKey(array $data, $bucket): string
    {
        return implode(':', [
            $data['type'],
            $data['name'],
            $this->encodeLabelValues($data['labelValues']),
            $bucket,
        ]);
    }
}
