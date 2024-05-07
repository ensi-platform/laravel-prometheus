<?php

namespace Ensi\LaravelPrometheus;

use Ensi\LaravelPrometheus\Controllers\MetricsController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class PrometheusServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(PrometheusManager::class);
        $this->app->alias(PrometheusManager::class, 'prometheus');

        $this->mergeConfigFrom(__DIR__.'/../config/prometheus.php', 'prometheus');
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/prometheus.php' => config_path('prometheus.php'),
            ], 'prometheus-config');
        }

        foreach (config('prometheus.bags') as $bagName => $bagConfig) {
            Route::get($bagConfig['route'], MetricsController::class)->name("prometheus.{$bagName}");
        }
    }
}
