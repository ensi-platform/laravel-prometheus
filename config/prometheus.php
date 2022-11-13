<?php

return [
    'defaults' => [
        'web' => 'default',
        'cli' => 'default',
    ],
    'bags' => [
        'default' => [
            'namespace' => env('PROMETHEUS_NAMESPACE', 'app'),
            'route' => '/metrics',
            'basic_auth' => [
                'login' => env('PROMETHEUS_AUTH_LOGIN'),
                'password' => env('PROMETHEUS_AUTH_PASSWORD'),
            ],
            'storage' => 'redis',
            'redis' => [
                'host' => env('PROMETHEUS_REDIS_HOST', '127.0.0.1'),
                'port' => env('PROMETHEUS_REDIS_PORT', 6379),
                'password' => env('PROMETHEUS_REDIS_PASSWORD'),
                'timeout' => 0.1,
                'read_timeout' => '10',
                'persistent_connections' => false,
            ],
            'apcu_prefix' => 'default',
            'label_providers' => [
                \Madridianfox\LaravelPrometheus\AppNameLabelProvider::class,
            ]
        ],
    ],
];