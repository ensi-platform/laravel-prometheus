<?php

namespace Ensi\LaravelPrometheus\Tests;

use Ensi\LaravelPrometheus\LabelMiddlewares\AppNameLabelMiddleware;
use Ensi\LaravelPrometheus\MetricsBag;
use Ensi\LaravelPrometheus\Tests\Fixstures\GlobalMiddleware;
use Ensi\LaravelPrometheus\Tests\Fixstures\LocalMiddleware;
use Ensi\LaravelPrometheus\Tests\Fixstures\SomeOnDemandMetric;
use Ensi\LaravelPrometheus\Tests\TestCases\MetricsBagTestCase;
use Illuminate\Http\Request;

uses(MetricsBagTestCase::class);

test('test metric help', function () {
    $bag = new MetricsBag([
        'namespace' => 'test',
        'memory' => true,
    ]);

    $bag->counter('my_counter')->help("Super metric")->update();

    $this->assertStringContainsString("# HELP test_my_counter Super metric", $bag->dumpTxt());
})->with(MetricsBagTestCase::labelsDataset());

test('test metric labels', function (array $labels) {
    $bag = new MetricsBag([
        'namespace' => 'test',
        'memory' => true,
    ]);

    $bag->counter('my_counter')->labels(array_keys($labels))->update(1, array_values($labels));
    $this->assertBagContainsMetric($bag, 'test_my_counter', $labels, 1);
})->with(MetricsBagTestCase::labelsDataset());

test('test global middleware', function (array $labels) {
    $bag = new MetricsBag([
        'namespace' => 'test',
        'memory' => true,
        'label_middlewares' => [
            GlobalMiddleware::class,
        ],
    ]);

    $bag->counter('my_counter')->labels(array_keys($labels))->update(1, array_values($labels));

    $labels = GlobalMiddleware::injectToMap($labels);

    $this->assertBagContainsMetric($bag, 'test_my_counter', $labels, 1);
})->with(MetricsBagTestCase::labelsDataset());

test('test local middleware', function (array $labels) {
    $bag = new MetricsBag([
        'namespace' => 'test',
        'memory' => true,
    ]);

    $bag->counter('my_counter')
        ->middleware(LocalMiddleware::class)
        ->labels(array_keys($labels))
        ->update(1, array_values($labels));

    $labels = LocalMiddleware::injectToMap($labels);

    $this->assertBagContainsMetric($bag, 'test_my_counter', $labels, 1);
})->with(MetricsBagTestCase::labelsDataset());

test('test both middlewares', function (array $labels) {
    $bag = new MetricsBag([
        'namespace' => 'test',
        'memory' => true,
        'label_middlewares' => [
            GlobalMiddleware::class,
        ],
    ]);

    $bag->counter('my_counter')
        ->middleware(LocalMiddleware::class)
        ->labels(array_keys($labels))
        ->update(1, array_values($labels));

    $labels = GlobalMiddleware::injectToMap($labels);
    $labels = LocalMiddleware::injectToMap($labels);

    $this->assertBagContainsMetric($bag, 'test_my_counter', $labels, 1);
})->with(MetricsBagTestCase::labelsDataset());

test('test counter', function () {
    $bag = new MetricsBag([
        'namespace' => 'test',
        'memory' => true,
    ]);

    $bag->counter('my_counter')->labels(['my_label']);

    $bag->update('my_counter', 1, ['my-value']);
    $this->assertBagContainsMetric($bag, 'test_my_counter', ["my_label" => "my-value"], 1);

    $bag->update('my_counter', 1, ['my-value']);
    $this->assertBagContainsMetric($bag, 'test_my_counter', ["my_label" => "my-value"], 2);

    $bag->update('my_counter', 2.5, ['my-value']);
    $this->assertBagContainsMetric($bag, 'test_my_counter', ["my_label" => "my-value"], 4.5);
});

test('test gauge', function () {
    $bag = new MetricsBag([
        'namespace' => 'test',
        'memory' => true,
    ]);

    $bag->gauge('my_gauge')->labels(['my_label']);

    $bag->update('my_gauge', 10, ['my-value']);
    $this->assertBagContainsMetric($bag, 'test_my_gauge', ["my_label" => "my-value"], 10);

    $bag->update('my_gauge', 5, ['my-value']);
    $this->assertBagContainsMetric($bag, 'test_my_gauge', ["my_label" => "my-value"], 5);
});

test('test histogram', function () {
    $bag = new MetricsBag([
        'namespace' => 'test',
        'memory' => true,
    ]);

    $bag->histogram('my_histogram', [2, 4])->labels(['my_label']);

    $bag->update('my_histogram', 3, ["my_label" => "my-value"]);
    $this->assertHistogramState($bag, 'test_my_histogram', ["my_label" => "my-value"], 3, 1, [
        2 => 0,
        4 => 1,
        "+Inf" => 1,
    ]);

    $bag->update('my_histogram', 5, ["my_label" => "my-value"]);
    $this->assertHistogramState($bag, 'test_my_histogram', ["my_label" => "my-value"], 8, 2, [
        2 => 0,
        4 => 1,
        "+Inf" => 2,
    ]);

    $bag->update('my_histogram', 1, ["my_label" => "my-value"]);
    $this->assertHistogramState($bag, 'test_my_histogram', ["my_label" => "my-value"], 9, 3, [
        2 => 1,
        4 => 2,
        "+Inf" => 3,
    ]);

    $bag->update('my_histogram', 50, ["my_label" => "my-value"]);
    $this->assertHistogramState($bag, 'test_my_histogram', ["my_label" => "my-value"], 59, 4, [
        2 => 1,
        4 => 2,
        "+Inf" => 4,
    ]);
});

test('test summary', function () {
    $bag = new MetricsBag([
        'namespace' => 'test',
        'memory' => true,
    ]);
    $bag->summary('my_summary', 120, [0.5, 0.9])->labels(['my_label']);

    $values = [];

    for ($i = 0; $i < 10; $i++) {
        $value = mt_rand(0, 100);
        $values[] = $value;
        $bag->update('my_summary', $value, ["my-value"]);
    }

    $this->assertSummaryState($bag, 'test_my_summary', ['my_label' => 'my-value'], array_sum($values), 10, [
        "0.5" => $this->getPercentile($values, 0.5),
        "0.9" => $this->getPercentile($values, 0.9),
    ]);
});

test('test no create same metric twice', function () {
    $bag = new MetricsBag([
        'namespace' => 'test',
        'memory' => true,
    ]);

    $counter1 = $bag->counter('my_counter');
    $counter2 = $bag->counter('my_counter');

    $this->assertSame($counter1, $counter2);
});

test('test label middleware', function () {
    config([
        'prometheus.app_name' => 'app-name',
    ]);

    $bag = new MetricsBag([
        'namespace' => 'test',
        'memory' => true,
        'label_middlewares' => [
            AppNameLabelMiddleware::class,
        ],
    ]);

    $bag->counter('my_counter')->labels(['my_label']);

    $bag->update('my_counter', 42, ['my-value']);
    $this->assertBagContainsMetric($bag, 'test_my_counter', ["my_label" => "my-value", "app" => "app-name"], 42);
});

test('test wipe storage', function () {
    $bag = new MetricsBag([
        'namespace' => 'test',
        'memory' => true,
    ]);

    $bag->counter('my_counter')->labels(['my_label']);

    $bag->update('my_counter', 42, ['my-value']);
    $this->assertBagContainsMetric($bag, 'test_my_counter', ["my_label" => "my-value"], 42);

    $bag->wipe();
    $bag->update('my_counter', 5, ['my-value']);
    $this->assertBagContainsMetric($bag, 'test_my_counter', ["my_label" => "my-value"], 5);
});

test('test bag auth', function () {
    $bag = new MetricsBag([
        'namespace' => 'test',
        'memory' => true,
        'basic_auth' => [
            'login' => 'user',
            'password' => 'secret',
        ],
    ]);

    $this->assertTrue($bag->auth(Request::create("http://user:secret@localhost/metrics")));
    $this->assertFalse($bag->auth(Request::create("http://user:123456@localhost/metrics")));
    $this->assertFalse($bag->auth(Request::create("http://bot:secret@localhost/metrics")));
    $this->assertFalse($bag->auth(Request::create("http://localhost/metrics")));
});

test('test on demand metrics', function () {
    $bag = new MetricsBag([
        'namespace' => 'test',
        'memory' => true,
        'on_demand_metrics' => [
            SomeOnDemandMetric::class,
        ],
    ]);

    $bag->processOnDemandMetrics();

    $this->assertBagContainsMetric($bag, 'test_on_demand_counter', [], 1);
});

test('test no updates when prometheus disabled', function () {
    config([
        'prometheus' => [
            'enabled' => false,
        ],
    ]);

    $bag = new MetricsBag([
        'namespace' => 'test',
        'memory' => true,
    ]);

    $bag->counter('my_counter')->labels(['my_label']);

    $bag->update('my_counter', 1, ['my-value']);

    $bagValues = $bag->dumpTxt();
    $this->assertStringNotContainsString('test_my_counter', $bagValues);
});
