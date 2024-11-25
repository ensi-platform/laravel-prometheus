<?php

namespace Ensi\LaravelPrometheus;

use Ensi\LaravelPrometheus\Controllers\MetricsController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class PrometheusServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/prometheus.php', 'prometheus');
        if (array_key_exists('octane-cache', config('prometheus.bags.'.config('prometheus.default_bag')))) {
            $this->mergeConfigFrom(__DIR__ . '/../config/octane.php', 'octane');
        }

        $this->app->singleton(PrometheusManager::class);
        $this->app->alias(PrometheusManager::class, 'prometheus');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/prometheus.php' => config_path('prometheus.php'),
            ], 'prometheus-config');
        }

        foreach (config('prometheus.bags') as $bagName => $bagConfig) {
            Route::get($bagConfig['route'], MetricsController::class)->name("prometheus.{$bagName}");
        }
    }
}
