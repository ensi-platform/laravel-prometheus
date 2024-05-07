<?php

namespace Ensi\LaravelPrometheus\LabelMiddlewares;

use function config;

class AppNameLabelMiddleware implements LabelMiddleware
{
    public function labels(): array
    {
        return ['app'];
    }

    public function values(): array
    {
        return [config('prometheus.app_name')];
    }
}
