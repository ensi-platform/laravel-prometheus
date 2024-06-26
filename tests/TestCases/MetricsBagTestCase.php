<?php

namespace Ensi\LaravelPrometheus\Tests\TestCases;

use Ensi\LaravelPrometheus\MetricsBag;
use Ensi\LaravelPrometheus\Tests\TestCase;

class MetricsBagTestCase extends TestCase
{
    protected function assertBagContainsMetric(MetricsBag $bag, string $metric, array $labels, $value): void
    {
        $labelItems = [];
        foreach ($labels as $label => $labelValue) {
            $labelItems[] = "{$label}=\"{$labelValue}\"";
        }

        if ($labelItems) {
            $labelsStr = join(",", $labelItems);
            $metricLine = "{$metric}{{$labelsStr}} {$value}";
        } else {
            $metricLine = "{$metric} {$value}";
        }

        $this->assertStringContainsString($metricLine, $bag->dumpTxt());
    }

    protected function assertHistogramState(MetricsBag $bag, string $metric, array $labels, int $sum, int $count, array $buckets): void
    {
        $this->assertBagContainsMetric($bag, $metric . '_sum', $labels, $sum);
        $this->assertBagContainsMetric($bag, $metric . '_count', $labels, $count);

        foreach ($buckets as $bucket => $value) {
            $bucketLabels = $labels;
            $bucketLabels['le'] = $bucket;
            $this->assertBagContainsMetric($bag, $metric . '_bucket', $bucketLabels, $value);
        }
    }

    protected function assertSummaryState(MetricsBag $bag, string $metric, array $labels, float $sum, float $count, array $quantiles): void
    {
        $this->assertBagContainsMetric($bag, $metric . '_sum', $labels, $sum);
        $this->assertBagContainsMetric($bag, $metric . '_count', $labels, $count);

        foreach ($quantiles as $quantile => $value) {
            $quantileLabels = $labels;
            $quantileLabels['quantile'] = $quantile;

            $this->assertBagContainsMetric($bag, $metric, $quantileLabels, $value);
        }
    }

    protected function getPercentile(array $values, float $level)
    {
        sort($values);
        $idealIndex = (count($values) - 1) * $level;
        $leftIndex = floor($idealIndex);

        return $values[$leftIndex];
    }

    public static function labelsDataset(): array
    {
        return [
            [[]],
            [['label' => 'value']],
            [['label_1' => 'value-1', 'label_2' => 'value-2']],
        ];
    }
}
