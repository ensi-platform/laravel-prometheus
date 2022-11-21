<?php

return [
    'default_bag' => 'default',
    'bags' => [
        'default' => [
            'namespace' => env('PROMETHEUS_NAMESPACE', 'app'),
            'route' => 'metrics',
            'basic_auth' => [
                'login' => env('PROMETHEUS_AUTH_LOGIN'),
                'password' => env('PROMETHEUS_AUTH_PASSWORD'),
            ],
            'connection' => [
                'connection' => 'default',
                'bag' => 'default',
            ],
            'label_middlewares' => [
                \Madridianfox\LaravelPrometheus\LabelMiddlewares\AppNameLabelMiddleware::class,
            ],
            'on_demand_metrics' => [
                \Madridianfox\LaravelPrometheus\OnDemandMetrics\MemoryUsageOnDemandMetric::class,
            ],
        ],
    ],
];