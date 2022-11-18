<?php

namespace Madridianfox\LaravelPrometheus\Tests;

use Illuminate\Http\Request;
use Madridianfox\LaravelPrometheus\AppNameLabelProvider;
use Madridianfox\LaravelPrometheus\MetricsBag;

class MetricsBagTest extends TestCase
{
    private function assertBagContains(MetricsBag $bag, string $metric, array $labels, $value): void
    {
        $labelItems = [];
        foreach ($labels as $label => $labelValue) {
            $labelItems[] = "{$label}=\"{$labelValue}\"";
        }
        $labelsStr = join(",", $labelItems);
        $metricLine = "{$metric}{{$labelsStr}} {$value}";

        $bagValues = $bag->dumpTxt();

        $this->assertStringContainsString($metricLine, $bagValues);
    }

    private function assertHistogramState(MetricsBag $bag, string $metric, array $labels, int $sum, int $count, array $buckets): void
    {
        $this->assertBagContains($bag, $metric . '_sum', $labels, $sum);
        $this->assertBagContains($bag, $metric . '_count', $labels, $count);

        foreach ($buckets as $bucket => $value) {
            $bucketLabels = $labels;
            $bucketLabels['le'] = $bucket;
            $this->assertBagContains($bag, $metric . '_bucket', $bucketLabels, $value);
        }
    }

    protected function assertSummaryState(MetricsBag $bag, string $metric, array $labels, float $sum, float $count, array $quantiles): void
    {
        $this->assertBagContains($bag, $metric . '_sum', $labels, $sum);
        $this->assertBagContains($bag, $metric . '_count', $labels, $count);

        foreach ($quantiles as $quantile => $value) {
            $quantileLabels = $labels;
            $quantileLabels['quantile'] = $quantile;

            $this->assertBagContains($bag, $metric, $quantileLabels, $value);
        }
    }

    public function testCounter()
    {
        $bag = new MetricsBag([
            'namespace' => 'test',
            'memory' => true,
        ]);

        $bag->declareCounter('my_counter', ['my_label']);

        $bag->updateCounter('my_counter', ['my-value']);
        $this->assertBagContains($bag, 'test_my_counter', ["my_label" => "my-value"], 1);

        $bag->updateCounter('my_counter', ['my-value']);
        $this->assertBagContains($bag, 'test_my_counter', ["my_label" => "my-value"], 2);

        $bag->updateCounter('my_counter', ['my-value'], 2.5);
        $this->assertBagContains($bag, 'test_my_counter', ["my_label" => "my-value"], 4.5);
    }

    public function testGauge()
    {
        $bag = new MetricsBag([
            'namespace' => 'test',
            'memory' => true,
        ]);

        $bag->declareGauge('my_gauge', ['my_label']);

        $bag->updateGauge('my_gauge', ['my-value'], 10);
        $this->assertBagContains($bag, 'test_my_gauge', ["my_label" => "my-value"], 10);

        $bag->updateGauge('my_gauge', ['my-value'], 5);
        $this->assertBagContains($bag, 'test_my_gauge', ["my_label" => "my-value"], 5);
    }

    public function testHistogram()
    {
        $bag = new MetricsBag([
            'namespace' => 'test',
            'memory' => true,
        ]);

        $bag->declareHistogram('my_histogram', [2, 4], ['my_label']);

        $bag->updateHistogram('my_histogram', ["my_label" => "my-value"], 3);
        $this->assertHistogramState($bag, 'test_my_histogram', ["my_label" => "my-value"], 3, 1, [
            2 => 0,
            4 => 1,
            "+Inf" => 1
        ]);

        $bag->updateHistogram('my_histogram', ["my_label" => "my-value"], 5);
        $this->assertHistogramState($bag, 'test_my_histogram', ["my_label" => "my-value"], 8, 2, [
            2 => 0,
            4 => 1,
            "+Inf" => 2
        ]);

        $bag->updateHistogram('my_histogram', ["my_label" => "my-value"], 1);
        $this->assertHistogramState($bag, 'test_my_histogram', ["my_label" => "my-value"], 9, 3, [
            2 => 1,
            4 => 2,
            "+Inf" => 3
        ]);

        $bag->updateHistogram('my_histogram', ["my_label" => "my-value"], 50);
        $this->assertHistogramState($bag, 'test_my_histogram', ["my_label" => "my-value"], 59, 4, [
            2 => 1,
            4 => 2,
            "+Inf" => 4
        ]);
    }

    protected function getPercentile(array $values, float $level)
    {
        sort($values);
        $idealIndex = (count($values) - 1) * $level;
        $leftIndex = floor($idealIndex);
        return $values[$leftIndex];
    }

    public function testSummary()
    {
        $bag = new MetricsBag([
            'namespace' => 'test',
            'memory' => true,
        ]);
        $bag->declareSummary('my_summary', 120, [0.5, 0.9], ['my_label']);

        $values = [];

        for ($i = 0; $i < 10; $i++) {
            $value = mt_rand(0, 100);
            $values[] = $value;
            $bag->updateSummary('my_summary', ["my-value"], $value);
        }

        $this->assertSummaryState($bag, 'test_my_summary', ['my_label' => 'my-value'], array_sum($values), 10, [
            "0.5" => $this->getPercentile($values, 0.5),
            "0.9" => $this->getPercentile($values, 0.9),
        ]);
    }

    public function testLabelProcessor()
    {
        config(['app.name' => 'app-name']);

        $bag = new MetricsBag([
            'namespace' => 'test',
            'memory' => true,
            'label_providers' => [
                AppNameLabelProvider::class,
            ]
        ]);

        $bag->declareCounter('my_counter', ['my_label']);

        $bag->updateCounter('my_counter', ['my-value'], 42);
        $this->assertBagContains($bag, 'test_my_counter', ["my_label" => "my-value", "app" => "app-name"], 42);
    }

    public function testWipeStorage()
    {
        $bag = new MetricsBag([
            'namespace' => 'test',
            'memory' => true,
        ]);

        $bag->declareCounter('my_counter', ['my_label']);

        $bag->updateCounter('my_counter', ['my-value'], 42);
        $this->assertBagContains($bag, 'test_my_counter', ["my_label" => "my-value"], 42);

        $bag->wipe();
        $bag->updateCounter('my_counter', ['my-value'], 5);
        $this->assertBagContains($bag, 'test_my_counter', ["my_label" => "my-value"], 5);
    }

    public function testBagAuth()
    {
        $bag = new MetricsBag([
            'namespace' => 'test',
            'memory' => true,
            'basic_auth' => [
                'login' => 'user',
                'password' => 'secret',
            ]
        ]);

        $this->assertTrue($bag->auth(Request::create("http://user:secret@localhost/metrics")));
        $this->assertFalse($bag->auth(Request::create("http://user:123456@localhost/metrics")));
        $this->assertFalse($bag->auth(Request::create("http://bot:secret@localhost/metrics")));
        $this->assertFalse($bag->auth(Request::create("http://localhost/metrics")));
    }
}