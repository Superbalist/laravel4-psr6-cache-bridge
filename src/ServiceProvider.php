<?php

namespace Superbalist\Laravel4PSR6CacheBridge;

use Illuminate\Cache\Repository;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Psr\Cache\CacheItemPoolInterface;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Register bindings in the container.
     */
    public function register()
    {
        $this->app->bind(CacheItemPoolInterface::class, LaravelCacheItemPool::class);

        $this->app->bind(LaravelCacheItemPool::class, function ($app) {
            $repository = $app->make(Repository::class);
            return new LaravelCacheItemPool($repository);
        });
    }
}
