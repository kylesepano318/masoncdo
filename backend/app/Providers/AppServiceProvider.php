<?php

namespace App\Providers;

use App\Mail\Transport\GmailApiTransport;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Mail::extend('gmail_api', fn (array $config) => new GmailApiTransport(
            (string) ($config['client_id'] ?? ''),
            (string) ($config['client_secret'] ?? ''),
            (string) ($config['refresh_token'] ?? ''),
            max(1, min(30, (int) ($config['timeout'] ?? 8))),
        ));
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
