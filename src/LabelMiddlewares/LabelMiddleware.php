<?php

namespace Ensi\LaravelPrometheus\LabelMiddlewares;

interface LabelMiddleware
{
    public function labels(): array;

    public function values(): array;
}
