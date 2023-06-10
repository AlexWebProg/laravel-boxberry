<?php
namespace Alexwebprog\LaravelBoxberry\Providers;

use Alexwebprog\LaravelBoxberry\Client;
use Illuminate\Support\ServiceProvider;

class BoxberryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/boxberry.php', 'boxberry');
        $this->app->singleton(Client::class, fn ($app) => new Client($app['config']['boxberry']));

    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../config/boxberry.php' => config_path('boxberry.php'),
        ], 'boxberry-config');
    }
}
