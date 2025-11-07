<?php

namespace Tigusigalpa\YandexLockbox\Laravel;

use Illuminate\Support\ServiceProvider;
use Tigusigalpa\YandexLockbox\Client;
use Tigusigalpa\YandexLockbox\Token\StaticTokenProvider;
use Tigusigalpa\YandexLockbox\Token\OAuthTokenProvider;

class LockboxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/lockbox.php', 'lockbox');

        $this->app->singleton(Client::class, function ($app) {
            $config = $app['config']['lockbox'] ?? [];
            $token = $config['token'] ?? '';
            $baseUri = $config['base_uri'] ?? null;

            // Determine token type and create appropriate provider
            // OAuth tokens start with 'y0_' or 'y1_' or 'y2_' or 'y3_'
            // IAM tokens start with 't1.' or are longer format
            $provider = $this->createTokenProvider($token);
            
            return new Client($provider, null, $baseUri);
        });

        // Alias for convenient resolution
        $this->app->alias(Client::class, 'lockbox');
    }

    /**
     * Create appropriate token provider based on token format
     * 
     * @param string $token
     * @return StaticTokenProvider|OAuthTokenProvider
     */
    private function createTokenProvider(string $token)
    {
        // OAuth tokens typically start with 'y0_', 'y1_', 'y2_', 'y3_'
        if (preg_match('/^y[0-3]_/', $token)) {
            return new OAuthTokenProvider($token);
        }

        // Otherwise treat as IAM token
        return new StaticTokenProvider($token);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/lockbox.php' => config_path('lockbox.php'),
            ], 'yandex-lockbox-config');

            // Register Artisan commands
            $this->commands([
                Commands\LockboxListCommand::class,
                Commands\LockboxShowCommand::class,
                Commands\LockboxCreateCommand::class,
                Commands\LockboxAddVersionCommand::class,
                Commands\LockboxDeleteCommand::class,
                Commands\LockboxTestCommand::class,
            ]);
        }
    }
}