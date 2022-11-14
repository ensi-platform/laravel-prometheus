<?php

namespace Madridianfox\LaravelPrometheus;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Madridianfox\LaravelPrometheus\Controllers\MetricsController;

class PrometheusServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(PrometheusManager::class);
        $this->mergeConfigFrom(__DIR__.'/../config/prometheus.php', 'prometheus');
    }

    public function boot()
    {
        foreach (config('prometheus.bags') as $bagConfig) {
            Route::get($bagConfig['route'], MetricsController::class);
        }
    }
}