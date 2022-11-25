<?php

namespace Madridianfox\LaravelPrometheus\Tests\Fixstures;

use Madridianfox\LaravelPrometheus\LabelMiddlewares\LabelMiddleware;

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