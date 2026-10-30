<?php

namespace App\Providers;

use App\Webhooks\PublicKeyResolver;
use App\Webhooks\WebhookVerifier;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use MailChannels\Client;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // SDK client for non-mail API calls (here: the webhook public key).
        // Mail itself goes through the SDK's Laravel mail driver.
        $this->app->singleton(Client::class, fn () => new Client(
            apiKey: config('services.mailchannels.api_key') ?: null,
            baseUrl: config('services.mailchannels.base_url'),
        ));

        $this->app->singleton(PublicKeyResolver::class, fn (Application $app) => new PublicKeyResolver(
            client: $app->make(Client::class),
            cache: $app->make('cache.store'),
            override: config('services.mailchannels.webhook_public_key'),
            cacheSeconds: (int) config('services.mailchannels.webhook_key_cache_seconds', 3600),
        ));

        $this->app->bind(WebhookVerifier::class, fn (Application $app) => new WebhookVerifier(
            keys: $app->make(PublicKeyResolver::class),
            maxAgeSeconds: (int) config('services.mailchannels.webhook_max_age_seconds', 300),
            maxFutureSeconds: (int) config('services.mailchannels.webhook_max_future_seconds', 60),
        ));
    }

    public function boot(): void
    {
        //
    }
}
