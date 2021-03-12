<?php

namespace jdavidbakr\CloudfrontProxies;

use Illuminate\Support\ServiceProvider;

class CloudfrontProxiesServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishConfig();
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/cloudfront-proxies.php',
            'cloudfront-proxies'
        );
    }

    protected function publishConfig()
    {
        $this->publishes([
            __DIR__.'/../config/cloudfront-proxies.php' => config_path('cloudfront-proxies.php')
        ], 'config');
    }
}
