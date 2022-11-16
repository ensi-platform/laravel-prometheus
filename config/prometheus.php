<?php

return [
    'defaults' => [
        'web' => 'default',
    ],
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
            'label_providers' => [
                \Madridianfox\LaravelPrometheus\AppNameLabelProvider::class,
            ]
        ],
    ],
];