<?php

namespace OpenCompany\Chatogrator;

use Illuminate\Support\ServiceProvider;

class ChatServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/chatogrator.php', 'chatogrator');

        $this->app->singleton(Chat::class, function () {
            return Chat::make('default');
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/chatogrator.php' => config_path('chatogrator.php'),
        ], 'chatogrator-config');

        $this->loadRoutesFrom(__DIR__.'/../routes/webhooks.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                Adapters\Discord\Gateway\DiscordGatewayCommand::class,
            ]);
        }
    }

    protected function loadRoutesFrom($path): void
    {
        $config = $this->app['config']->get('chatogrator', []);
        $prefix = $config['route_prefix'] ?? 'webhooks/chat';
        $middleware = $config['middleware'] ?? [];

        $this->app['router']
            ->prefix($prefix)
            ->middleware($middleware)
            ->group($path);
    }
}
