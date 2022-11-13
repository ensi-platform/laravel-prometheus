<?php

namespace Madridianfox\LaravelPrometheus;

class AppNameLabelProvider implements LabelProvider
{
    public function labels(): array
    {
        return ['app'];
    }

    public function values(): array
    {
        return [config('app.name')];
    }
}