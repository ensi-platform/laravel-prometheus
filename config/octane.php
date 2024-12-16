<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Octane Swoole Tables
    |--------------------------------------------------------------------------
    |
    | While using Swoole, you may define additional tables as required by the
    | application. These tables can be used to store data that needs to be
    | quickly accessed by other workers on the particular Swoole server.
    |
    */

    'tables' => [
        'gauges:1000' => [
            'meta' => 'string:10000',
            'valueKeys' => 'string:10000',
        ],
        'gauge_values:10000' => [
            'value' => 'float',
            'key' => 'string:10000',
        ],
        
        'сounters:1000' => [
            'meta' => 'string:10000',
            'valueKeys' => 'string:10000',
        ],
        'сounter_values:10000' => [
            'value' => 'float',
            'key' => 'string:10000',
        ],

        'summaries:1000' => [
            'meta' => 'string:10000',
            'valueKeys' => 'string:10000',
        ],
        'summary_values:10000' => [
            'key' => 'string:10000',
            'sampleTimes' => 'string:10000',
            'sampleValues' => 'string:10000',
        ],

        'histograms:1000' => [
            'meta' => 'string:10000',
            'valueKeys' => 'string:10000',
        ],
        'histogram_values:10000' => [
            'value' => 'float',
            'key' => 'string:10000',
        ],
    ],
];
