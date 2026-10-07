<?php

namespace App\Providers;

use App\Mail\Transport\GmailApiTransport;
use App\Mail\Transport\MailtrapApiTransport;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
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
        Mail::extend('mailtrap_api', fn (array $config) => new MailtrapApiTransport(
            (string) ($config['token'] ?? ''), max(1, min(30, (int) ($config['timeout'] ?? 15))),
        ));
        DB::listen(function (QueryExecuted $query) {
            $metrics = $this->app['request']->attributes->get('lodge.query_metrics');
            if ($metrics) {
                $metrics->queries++;
                $metrics->milliseconds += $query->time;
            }
        });
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
