<?php

declare(strict_types=1);

namespace RSFlowHub\Laravel;

use Illuminate\Support\ServiceProvider;
use RSFlowHub\RSFlowHub;

/**
 * Laravel integration (auto-discovered). Reads config('services.rsflowhub'):
 *
 *   // config/services.php
 *   'rsflowhub' => [
 *       'key' => env('RSFLOWHUB_API_KEY'),
 *       'base_url' => env('RSFLOWHUB_BASE_URL', 'https://rsflowhub.com/api/v1'),
 *   ],
 *
 * Then inject RSFlowHub\RSFlowHub or use the RSFlowHub facade.
 */
final class RSFlowHubServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RSFlowHub::class, function ($app): RSFlowHub {
            $config = (array) $app['config']->get('services.rsflowhub', []);

            return new RSFlowHub(
                $config['key'] ?? null,
                array_filter([
                    'base_url' => $config['base_url'] ?? null,
                    'timeout' => $config['timeout'] ?? null,
                    'max_retries' => $config['max_retries'] ?? null,
                ], fn ($value) => $value !== null)
            );
        });

        $this->app->alias(RSFlowHub::class, 'rsflowhub');
    }
}
