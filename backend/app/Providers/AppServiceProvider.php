<?php

namespace App\Providers;

use App\Support\Arquivo\Contracts\ArmazenarArquivo;
use App\Support\Arquivo\PipelineArquivo;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ArmazenarArquivo::class, PipelineArquivo::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
