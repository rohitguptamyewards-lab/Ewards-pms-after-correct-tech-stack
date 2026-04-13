<?php

namespace App\Providers;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Register Brevo mail transport
        Mail::extend('brevo', function () {
            $apiKey = config('services.brevo.key');
            $factory = new BrevoTransportFactory();
            return $factory->create(new Dsn('brevo+api', 'default', $apiKey));
        });

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
