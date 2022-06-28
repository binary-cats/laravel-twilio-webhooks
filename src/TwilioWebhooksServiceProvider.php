<?php

namespace BinaryCats\TwilioWebhooks;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class TwilioWebhooksServiceProvider extends ServiceProvider
{
    /**
     * Boot application services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/twilio-webhooks.php' => config_path('twilio-webhooks.php'),
            ], 'config');
        }

        Route::macro('twilioWebhooks', function ($url) {
            return Route::post($url, '\BinaryCats\TwilioWebhooks\TwilioWebhooksController');
        });
    }

    /**
     * Register application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/twilio-webhooks.php', 'twilio-webhooks');
    }
}
