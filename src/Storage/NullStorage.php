<?php

namespace Ensi\LaravelPrometheus\Storage;

use Prometheus\Storage\Adapter;

class NullStorage implements Adapter
{
    public function collect(): array
    {
        return [];
    }

    public function updateSummary(array $data): void
    {
    }

    public function updateHistogram(array $data): void
    {
    }

    public function updateGauge(array $data): void
    {
    }

    public function updateCounter(array $data): void
    {
    }

    public function wipeStorage(): void
    {
    }
}