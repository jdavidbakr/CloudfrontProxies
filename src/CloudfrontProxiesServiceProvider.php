<?php

namespace jdavidbakr\CloudfrontProxies;

use Illuminate\Support\ServiceProvider;

class CloudfrontProxiesServiceProvider extends ServiceProvider
{
    /**
     * Publish the configuration files
     *
     * @return void
     */
    protected function publishConfig()
    {
        if (!$this->isLumen()) {
            $this->publishes([
                __DIR__.'/../config/cloudfront-proxies.php' => config_path('cloudfront-proxies.php')
            ], 'config');
        }
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/cloudfront-proxies.php',
            'cloudfront-proxies'
        );
    }
}
