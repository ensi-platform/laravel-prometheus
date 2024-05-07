<?php

declare(strict_types=1);

namespace Ensi\LaravelPrometheus\Storage;

use InvalidArgumentException;
use Prometheus\Counter;
use Prometheus\Exception\StorageException;
use Prometheus\Gauge;
use Prometheus\Histogram;
use Prometheus\Math;
use Prometheus\MetricFamilySamples;
use Prometheus\Storage\Adapter;
use Prometheus\Summary;
use RedisException;
use RuntimeException;

class Redis implements Adapter
{
    const PROMETHEUS_METRIC_KEYS_SUFFIX = '_METRIC_KEYS';

    /**
     * @var mixed[]
     */
    private static $defaultOptions = [
        'host' => '127.0.0.1',
        'port' => 6379,
        'timeout' => 0.1,
        'read_timeout' => '10',
        'persistent_connections' => false,
        'password' => null,
    ];

    /**
     * @var string
     */
    private $prefix = 'PROMETHEUS_';

    /**
     * @var mixed[]
     */
    private $options = [];

    /**
     * @var \Redis
     */
    private $redis;

    /**
     * @var bool
     */
    private $connectionInitialized = false;

    /**
     * Redis constructor.
     * @param mixed[] $options
     */
    public function __construct(array $options = [])
    {
        $this->options = array_merge(self::$defaultOptions, $options);

        if (isset($this->options['bag'])) {
            $this->prefix = $this->options['bag'];
        }

        $this->redis = new \Redis();
    }

    /**
     * @param \Redis $redis
     * @return self
     * @throws StorageException
     */
    public static function fromExistingConnection(\Redis $redis, array $options = []): self
    {
        if ($redis->isConnected() === false) {
            throw new StorageException('Connection to Redis server not established');
        }

        $self = new self($options);
        $self->connectionInitialized = true;
        $self->redis = $redis;

        return $self;
    }

    /**
     * @param mixed[] $options
     */
    public static function setDefaultOptions(array $options): void
    {
        self::$defaultOptions = array_merge(self::$defaultOptions, $options);
    }

    /**
     * @param string $prefix
     */
    public function setPrefix(string $prefix): void
    {
        $this->prefix = $prefix;
    }

    /**
     * @deprecated use replacement method wipeStorage from Adapter interface
     * @throws StorageException
     */
    public function flushRedis(): void
    {
        $this->wipeStorage();
    }

    /**
     * @return string
     * @throws RedisException
     */
    public function getGlobalPrefix(): string
    {
        $globalPrefix = $this->redis->getOption(\Redis::OPT_PREFIX);
        // @phpstan-ignore-next-line false positive, phpstan thinks getOptions returns int
        if (!is_string($globalPrefix)) {
            return '';
        }

        return $globalPrefix;
    }

    /**
     * @inheritDoc
     */
    public function wipeStorage(): void
    {
        $this->ensureOpenConnection();

        $searchPattern = $this->getGlobalPrefix();

        $searchPattern .= $this->prefix;
        $searchPattern .= '*';

        $this->redis->eval(
            <<<LUA
local cursor = "0"
repeat 
    local results = redis.call('SCAN', cursor, 'MATCH', ARGV[1])
    cursor = results[1]
    for _, key in ipairs(results[2]) do
        redis.call('DEL', key)
    end
until cursor == "0"
LUA
            ,
            [$searchPattern],
            0
        );
    }

    /**
     * @param mixed[] $data
     *
     * @return string
     */
    private function metaKey(array $data): string
    {
        return implode(':', [
            $data['name'],
            'meta',
        ]);
    }

    /**
     * @param mixed[] $data
     *
     * @return string
     */
    private function valueKey(array $data): string
    {
        return implode(':', [
            $data['name'],
            $this->encodeLabelValues($data['labelValues']),
            'value',
        ]);
    }

    /**
     * @return MetricFamilySamples[]
     * @throws StorageException
     */
    public function collect(): array
    {
        $this->ensureOpenConnection();
        $metrics = $this->collectHistograms();
        $metrics = array_merge($metrics, $this->collectGauges());
        $metrics = array_merge($metrics, $this->collectCounters());
        $metrics = array_merge($metrics, $this->collectSummaries());

        return array_map(
            function (array $metric): MetricFamilySamples {
                return new MetricFamilySamples($metric);
            },
            $metrics
        );
    }

    /**
     * @throws StorageException
     */
    private function ensureOpenConnection(): void
    {
        if ($this->connectionInitialized === true) {
            return;
        }

        $this->connectToServer();

        if ($this->options['password'] !== null) {
            $this->redis->auth($this->options['password']);
        }

        if (isset($this->options['database'])) {
            $this->redis->select($this->options['database']);
        }

        if (isset($this->options['prefix'])) {
            $this->redis->setOption(\Redis::OPT_PREFIX, $this->options['prefix']);
        }

        $this->redis->setOption(\Redis::OPT_READ_TIMEOUT, $this->options['read_timeout']);

        $this->connectionInitialized = true;
    }

    /**
     * @throws StorageException
     */
    private function connectToServer(): void
    {
        try {
            $connection_successful = false;
            if ($this->options['persistent_connections'] !== false) {
                $connection_successful = $this->redis->pconnect(
                    $this->options['host'],
                    (int) $this->options['port'],
                    (float) $this->options['timeout']
                );
            } else {
                $connection_successful = $this->redis->connect($this->options['host'], (int) $this->options['port'], (float) $this->options['timeout']);
            }
            if (!$connection_successful) {
                throw new StorageException("Can't connect to Redis server", 0);
            }
        } catch (\RedisException $e) {
            throw new StorageException("Can't connect to Redis server", 0, $e);
        }
    }

    /**
     * @param mixed[] $data
     * @throws StorageException
     */
    public function updateHistogram(array $data): void
    {
        $this->ensureOpenConnection();
        $bucketToIncrease = '+Inf';
        foreach ($data['buckets'] as $bucket) {
            if ($data['value'] <= $bucket) {
                $bucketToIncrease = $bucket;

                break;
            }
        }
        $metaData = $data;
        unset($metaData['value'], $metaData['labelValues']);

        $wasRemoved = $this->removeMetricOnLabelDiff($data);

        $this->redis->eval(
            <<<LUA
local result = redis.call('hIncrByFloat', KEYS[1], ARGV[1], ARGV[3])
redis.call('hIncrBy', KEYS[1], ARGV[2], 1)
if tonumber(result) >= tonumber(ARGV[3]) or ARGV[5] == '1' then
    redis.call('hSet', KEYS[1], '__meta', ARGV[4])
    redis.call('sAdd', KEYS[2], KEYS[1])
end
return result
LUA
            ,
            [
                $this->toMetricKey($data),
                $this->prefix . Histogram::TYPE . self::PROMETHEUS_METRIC_KEYS_SUFFIX,
                json_encode(['b' => 'sum', 'labelValues' => $data['labelValues']]),
                json_encode(['b' => $bucketToIncrease, 'labelValues' => $data['labelValues']]),
                $data['value'],
                json_encode($metaData),
                $wasRemoved,
            ],
            2
        );
    }

    /**
     * @param mixed[] $data
     * @throws StorageException
     */
    public function updateSummary(array $data): void
    {
        $this->ensureOpenConnection();

        $this->removeMetricOnLabelDiff($data);

        // store meta
        $summaryKey = $this->prefix . Summary::TYPE . self::PROMETHEUS_METRIC_KEYS_SUFFIX;
        $metaKey = $summaryKey . ':' . $this->metaKey($data);
        $json = json_encode($this->metaData($data));
        if (false === $json) {
            throw new RuntimeException(json_last_error_msg());
        }
        $this->redis->setNx($metaKey, $json);  /** @phpstan-ignore-line */

        // store value key
        $valueKey = $summaryKey . ':' . $this->valueKey($data);
        $json = json_encode($this->encodeLabelValues($data['labelValues']));
        if (false === $json) {
            throw new RuntimeException(json_last_error_msg());
        }
        $this->redis->setNx($valueKey, $json); /** @phpstan-ignore-line */

        // trick to handle uniqid collision
        $done = false;
        while (!$done) {
            $sampleKey = $valueKey . ':' . uniqid('', true);
            $done = $this->redis->set($sampleKey, $data['value'], ['NX', 'EX' => $data['maxAgeSeconds']]);
        }
    }

    /**
     * @param mixed[] $data
     * @throws StorageException
     */
    public function updateGauge(array $data): void
    {
        $this->ensureOpenConnection();
        $metaData = $data;
        unset($metaData['value'], $metaData['labelValues'], $metaData['command']);

        $wasRemoved = $this->removeMetricOnLabelDiff($data);

        $this->redis->eval(
            <<<LUA
local result = redis.call(ARGV[1], KEYS[1], ARGV[2], ARGV[3])

if ARGV[1] == 'hSet' then
    if result == 1 or ARGV[5] == '1' then
        redis.call('hSet', KEYS[1], '__meta', ARGV[4])
        redis.call('sAdd', KEYS[2], KEYS[1])
    end
else
    if result == ARGV[3] or ARGV[5] == '1' then
        redis.call('hSet', KEYS[1], '__meta', ARGV[4])
        redis.call('sAdd', KEYS[2], KEYS[1])
    end
end
LUA
            ,
            [
                $this->toMetricKey($data),
                $this->prefix . Gauge::TYPE . self::PROMETHEUS_METRIC_KEYS_SUFFIX,
                $this->getRedisCommand($data['command']),
                json_encode($data['labelValues']),
                $data['value'],
                json_encode($metaData),
                $wasRemoved,
            ],
            2
        );
    }

    /**
     * @param mixed[] $data
     * @throws StorageException
     */
    public function updateCounter(array $data): void
    {
        $this->ensureOpenConnection();
        $metaData = $data;
        unset($metaData['value'], $metaData['labelValues'], $metaData['command']);

        $wasRemoved = $this->removeMetricOnLabelDiff($data);

        $this->redis->eval(
            <<<LUA
local result = redis.call(ARGV[1], KEYS[1], ARGV[3], ARGV[2])
local added = redis.call('sAdd', KEYS[2], KEYS[1])
if added == 1 or ARGV[5] == '1' then
    redis.call('hMSet', KEYS[1], '__meta', ARGV[4])
end
return result
LUA
            ,
            [
                $this->toMetricKey($data),
                $this->prefix . Counter::TYPE . self::PROMETHEUS_METRIC_KEYS_SUFFIX,
                $this->getRedisCommand($data['command']),
                $data['value'],
                json_encode($data['labelValues']),
                json_encode($metaData),
                $wasRemoved,
            ],
            2
        );
    }

    private function removeMetricOnLabelDiff(array $data): bool
    {
        $metrics = match ($data['type']) {
            Counter::TYPE => $this->collectCounters(),
            Gauge::TYPE => $this->collectGauges(),
            Histogram::TYPE => $this->collectHistograms(),
            Summary::TYPE => $this->collectSummaries(),
            default => [],
        };

        foreach ($metrics as $metric) {
            if ($metric['name'] != $data['name']) {
                continue;
            }

            if (
                $this->isMetricLabelNamesIdentical($data['labelNames'], $metric['labelNames']) &&
                !$this->issetMetricWithSuchLabelValues($data['labelValues'], $metric['samples'] ?? [])
            ) {
                return false;
            }

            return $this->removeMetric($data['type'], $metric);
        }

        return false;
    }

    private function isMetricLabelNamesIdentical(array $labelNames, array $existingMetricLabelNames): bool
    {
        $labelDiff1 = count(array_diff($existingMetricLabelNames, $labelNames));
        $labelDiff2 = count(array_diff($labelNames, $existingMetricLabelNames));

        if (
            ($labelDiff1 == 0 && $labelDiff2 == 0) ||
            ($labelDiff1 == count($existingMetricLabelNames) && $labelDiff2 == count($labelNames))
        ) {
            return true;
        }

        return false;
    }

    /**
     * Существует ли метрика с таким же labelValues, но с разными ключами
     */
    private function issetMetricWithSuchLabelValues(array $labelValues, array $existingMetricSamples): bool
    {
        foreach ($existingMetricSamples as $sample) {
            if (
                count(array_diff($labelValues, $sample['labelValues'])) == 0 &&
                count(array_diff($sample['labelValues'], $labelValues)) == 0 &&
                (count(array_diff_key($labelValues, $sample['labelValues'])) > 0 || count(array_diff_key($sample['labelValues'], $labelValues)) > 0)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws \RedisException
     */
    public function removeMetric(string $type, array $data): bool
    {
        $metricKeyForDelete = $this->getGlobalPrefix() . $this->prefix;
        $metricKeyForDelete .= match ($data['type']) {
            Summary::TYPE => $type . implode(':', [self::PROMETHEUS_METRIC_KEYS_SUFFIX, $data['name']]). ':*',
            default => ':' . implode(':', [$type, $data['name']]),
        };

        $result =  boolval($this->redis->eval(
            <<<LUA
            local cursor = "0"
            local delResult = "0"
            repeat
                local results = redis.call('SCAN', cursor, 'MATCH', ARGV[1])
                cursor = results[1]
                for _, key in ipairs(results[2]) do
                    delResult = delResult + redis.call('DEL', key)
                end
            until cursor == "0"
            return delResult
LUA
            ,
            [$metricKeyForDelete]
        ));

        $error = $this->redis->getLastError();
        if (!$result && $error != null) {
            throw new RuntimeException($error);
        }

        return $result;
    }

    /**
     * @param mixed[] $data
     * @return mixed[]
     */
    private function metaData(array $data): array
    {
        $metricsMetaData = $data;
        unset($metricsMetaData['value'], $metricsMetaData['command'], $metricsMetaData['labelValues']);

        return $metricsMetaData;
    }

    /**
     * @return mixed[]
     */
    private function collectHistograms(): array
    {
        $keys = $this->redis->sMembers($this->prefix . Histogram::TYPE . self::PROMETHEUS_METRIC_KEYS_SUFFIX);
        sort($keys);
        $histograms = [];
        foreach ($keys as $key) {
            $raw = $this->redis->hGetAll(str_replace($this->redis->_prefix(''), '', $key));
            $histogram = json_decode($raw['__meta'], true);
            unset($raw['__meta']);
            $histogram['samples'] = [];

            // Add the Inf bucket so we can compute it later on
            $histogram['buckets'][] = '+Inf';

            $allLabelValues = [];
            foreach (array_keys($raw) as $k) {
                $d = json_decode($k, true);
                if ($d['b'] == 'sum') {
                    continue;
                }
                $allLabelValues[] = $d['labelValues'];
            }

            // We need set semantics.
            // This is the equivalent of array_unique but for arrays of arrays.
            $allLabelValues = array_map("unserialize", array_unique(array_map("serialize", $allLabelValues)));
            sort($allLabelValues);

            foreach ($allLabelValues as $labelValues) {
                // Fill up all buckets.
                // If the bucket doesn't exist fill in values from
                // the previous one.
                $acc = 0;
                foreach ($histogram['buckets'] as $bucket) {
                    $bucketKey = json_encode(['b' => $bucket, 'labelValues' => $labelValues]);
                    if (!isset($raw[$bucketKey])) {
                        $histogram['samples'][] = [
                            'name' => $histogram['name'] . '_bucket',
                            'labelNames' => ['le'],
                            'labelValues' => array_merge($labelValues, [$bucket]),
                            'value' => $acc,
                        ];
                    } else {
                        $acc += $raw[$bucketKey];
                        $histogram['samples'][] = [
                            'name' => $histogram['name'] . '_bucket',
                            'labelNames' => ['le'],
                            'labelValues' => array_merge($labelValues, [$bucket]),
                            'value' => $acc,
                        ];
                    }
                }

                // Add the count
                $histogram['samples'][] = [
                    'name' => $histogram['name'] . '_count',
                    'labelNames' => [],
                    'labelValues' => $labelValues,
                    'value' => $acc,
                ];

                // Add the sum
                $histogram['samples'][] = [
                    'name' => $histogram['name'] . '_sum',
                    'labelNames' => [],
                    'labelValues' => $labelValues,
                    'value' => $raw[json_encode(['b' => 'sum', 'labelValues' => $labelValues])],
                ];
            }
            $histograms[] = $histogram;
        }

        return $histograms;
    }

    /**
     * @param string $key
     *
     * @return string
     */
    private function removePrefixFromKey(string $key): string
    {
        // @phpstan-ignore-next-line false positive, phpstan thinks getOptions returns int
        if ($this->redis->getOption(\Redis::OPT_PREFIX) === null) {
            return $key;
        }

        // @phpstan-ignore-next-line false positive, phpstan thinks getOptions returns int
        return substr($key, strlen($this->redis->getOption(\Redis::OPT_PREFIX)));
    }

    /**
     * @return mixed[]
     */
    private function collectSummaries(): array
    {
        $math = new Math();
        $summaryKey = $this->prefix . Summary::TYPE . self::PROMETHEUS_METRIC_KEYS_SUFFIX;
        $keys = $this->redis->keys($summaryKey . ':*:meta');

        $summaries = [];
        foreach ($keys as $metaKeyWithPrefix) {
            $metaKey = $this->removePrefixFromKey($metaKeyWithPrefix);
            $rawSummary = $this->redis->get($metaKey);
            if ($rawSummary === false) {
                continue;
            }
            $summary = json_decode($rawSummary, true);
            $metaData = $summary;
            $data = [
                'name' => $metaData['name'],
                'help' => $metaData['help'],
                'type' => $metaData['type'],
                'labelNames' => $metaData['labelNames'],
                'maxAgeSeconds' => $metaData['maxAgeSeconds'],
                'quantiles' => $metaData['quantiles'],
                'samples' => [],
            ];

            $values = $this->redis->keys($summaryKey . ':' . $metaData['name'] . ':*:value');
            foreach ($values as $valueKeyWithPrefix) {
                $valueKey = $this->removePrefixFromKey($valueKeyWithPrefix);
                $rawValue = $this->redis->get($valueKey);
                if ($rawValue === false) {
                    continue;
                }
                $value = json_decode($rawValue, true);
                $encodedLabelValues = $value;
                $decodedLabelValues = $this->decodeLabelValues($encodedLabelValues);

                $samples = [];
                $sampleValues = $this->redis->keys($summaryKey . ':' . $metaData['name'] . ':' . $encodedLabelValues . ':value:*');
                foreach ($sampleValues as $sampleValueWithPrefix) {
                    $sampleValue = $this->removePrefixFromKey($sampleValueWithPrefix);
                    $samples[] = (float) $this->redis->get($sampleValue);
                }

                if (count($samples) === 0) {
                    $this->redis->del($valueKey);

                    continue;
                }

                // Compute quantiles
                sort($samples);
                foreach ($data['quantiles'] as $quantile) {
                    $data['samples'][] = [
                        'name' => $metaData['name'],
                        'labelNames' => ['quantile'],
                        'labelValues' => array_merge($decodedLabelValues, [$quantile]),
                        'value' => $math->quantile($samples, $quantile),
                    ];
                }

                // Add the count
                $data['samples'][] = [
                    'name' => $metaData['name'] . '_count',
                    'labelNames' => [],
                    'labelValues' => $decodedLabelValues,
                    'value' => count($samples),
                ];

                // Add the sum
                $data['samples'][] = [
                    'name' => $metaData['name'] . '_sum',
                    'labelNames' => [],
                    'labelValues' => $decodedLabelValues,
                    'value' => array_sum($samples),
                ];
            }

            if (count($data['samples']) > 0) {
                $summaries[] = $data;
            } else {
                $this->redis->del($metaKey);
            }
        }

        return $summaries;
    }

    /**
     * @return mixed[]
     */
    private function collectGauges(): array
    {
        $keys = $this->redis->sMembers($this->prefix . Gauge::TYPE . self::PROMETHEUS_METRIC_KEYS_SUFFIX);
        sort($keys);
        $gauges = [];
        foreach ($keys as $key) {
            $raw = $this->redis->hGetAll(str_replace($this->redis->_prefix(''), '', $key));
            $gauge = json_decode($raw['__meta'], true);
            unset($raw['__meta']);
            $gauge['samples'] = [];
            foreach ($raw as $k => $value) {
                $gauge['samples'][] = [
                    'name' => $gauge['name'],
                    'labelNames' => [],
                    'labelValues' => json_decode($k, true),
                    'value' => $value,
                ];
            }
            usort($gauge['samples'], function ($a, $b): int {
                return strcmp(implode("", $a['labelValues']), implode("", $b['labelValues']));
            });
            $gauges[] = $gauge;
        }

        return $gauges;
    }

    /**
     * @return mixed[]
     */
    private function collectCounters(): array
    {
        $keys = $this->redis->sMembers($this->prefix . Counter::TYPE . self::PROMETHEUS_METRIC_KEYS_SUFFIX);
        sort($keys);
        $counters = [];
        foreach ($keys as $key) {
            $raw = $this->redis->hGetAll(str_replace($this->redis->_prefix(''), '', $key));
            $counter = json_decode($raw['__meta'], true);
            unset($raw['__meta']);
            $counter['samples'] = [];
            foreach ($raw as $k => $value) {
                $counter['samples'][] = [
                    'name' => $counter['name'],
                    'labelNames' => [],
                    'labelValues' => json_decode($k, true),
                    'value' => $value,
                ];
            }
            usort($counter['samples'], function ($a, $b): int {
                return strcmp(implode("", $a['labelValues']), implode("", $b['labelValues']));
            });
            $counters[] = $counter;
        }

        return $counters;
    }

    /**
     * @param int $cmd
     * @return string
     */
    private function getRedisCommand(int $cmd): string
    {
        switch ($cmd) {
            case Adapter::COMMAND_INCREMENT_INTEGER:
                return 'hIncrBy';
            case Adapter::COMMAND_INCREMENT_FLOAT:
                return 'hIncrByFloat';
            case Adapter::COMMAND_SET:
                return 'hSet';
            default:
                throw new InvalidArgumentException("Unknown command");
        }
    }

    /**
     * @param mixed[] $data
     * @return string
     */
    private function toMetricKey(array $data): string
    {
        return implode(':', [$this->prefix, $data['type'], $data['name']]);
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
}
