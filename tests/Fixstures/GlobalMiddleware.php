<?php

namespace Ensi\LaravelPrometheus\Tests\Fixstures;

use Ensi\LaravelPrometheus\LabelMiddlewares\LabelMiddleware;

class GlobalMiddleware extends AbstractTestingMiddleware implements LabelMiddleware
{
    public function labels(): array
    {
        return ['global_label'];
    }

    public function values(): array
    {
        return ['global-value'];
    }
}