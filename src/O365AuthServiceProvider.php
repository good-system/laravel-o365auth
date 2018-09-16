<?php

namespace Singingfox\O365Auth;

use Illuminate\Support\ServiceProvider;

class O365AuthServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        include __DIR__.'/routes.php';
        $this->loadViewsFrom(__DIR__.'/views', 'O365Auth');
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->make('Singingfox\O365Auth\OAuthController');
        $this->mergeConfigFrom(__DIR__.'/config.php', 'O365Auth');
    }
}
