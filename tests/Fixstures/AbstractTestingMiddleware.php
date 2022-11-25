<?php

namespace Madridianfox\LaravelPrometheus\Tests\Fixstures;

use Madridianfox\LaravelPrometheus\LabelMiddlewares\LabelMiddleware;

class AbstractTestingMiddleware
{
    public static function injectToMap(array $labels): array
    {
        /** @var LabelMiddleware $middleware */
        $middleware = resolve(static::class);
        $globalLabels = $middleware->labels();
        $globalValues = $middleware->values();
        foreach ($globalLabels as $i => $globalLabel) {
            $labels[$globalLabel] = $globalValues[$i];
        }

        return $labels;
    }
}