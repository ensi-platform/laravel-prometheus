<?php

namespace Madridianfox\LaravelPrometheus;

interface LabelProvider
{
    public function labels(): array;
    public function values(): array;
}