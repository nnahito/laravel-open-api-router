<?php

declare(strict_types=1);

namespace Nnahito\LaravelOpenApiRouter;

use Illuminate\Support\ServiceProvider;

class LaravelOpenApiRouterServiceProvider extends ServiceProvider
{
    public function register() {}

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Router2OpenApi::class,
            ]);
        }
    }
}
