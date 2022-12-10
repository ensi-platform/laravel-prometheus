<?php

namespace Ensi\LaravelPrometheus\Tests\Fixstures;

use Ensi\LaravelPrometheus\LabelMiddlewares\LabelMiddleware;

class LocalMiddleware extends AbstractTestingMiddleware implements LabelMiddleware
{
    public function labels(): array
    {
        return ['local_label'];
    }

    public function values(): array
    {
        return ['local-value'];
    }
}