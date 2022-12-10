<?php

return [
    'default_bag' => 'default',
    'enabled' => env('PROMETHEUS_ENABLED', true),
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
                \Ensi\LaravelPrometheus\LabelMiddlewares\AppNameLabelMiddleware::class,
            ],
            'on_demand_metrics' => [
                \Ensi\LaravelPrometheus\OnDemandMetrics\MemoryUsageOnDemandMetric::class,
            ],
        ],
    ],
];