<?php

namespace Madridianfox\LaravelPrometheus\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Madridianfox\LaravelPrometheus\MetricsBag;
use Madridianfox\LaravelPrometheus\PrometheusManager;
use Prometheus\RenderTextFormat;

class MetricsController
{
    public function __invoke(Request $request, PrometheusManager $prometheus)
    {
        $metricsBag = $this->getMetricsBagForPath($request, $prometheus);
        if (!$metricsBag->auth($request)) {
            abort(401, "Authentication required", ['WWW-Authenticate' => 'Basic']);
        }

        return new Response($metricsBag->dumpTxt(), 200, ['Content-type' => RenderTextFormat::MIME_TYPE]);
    }

    private function getMetricsBagForPath(Request $request, PrometheusManager $prometheus): MetricsBag
    {
        $requestPath = $request->path();
        foreach (config('prometheus.bags') as $bagName => $bagConfig) {
            if ($bagConfig['route'] == $requestPath) {
                return $prometheus->bag($bagName);
            }
        }

        abort(404);
    }
}