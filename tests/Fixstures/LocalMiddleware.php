<?php

namespace Madridianfox\LaravelPrometheus\Tests\Fixstures;

use Madridianfox\LaravelPrometheus\LabelMiddlewares\LabelMiddleware;

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