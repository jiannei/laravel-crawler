<?php

namespace Jiannei\LaravelCrawler\Providers;

use Jiannei\LaravelCrawler\Contracts\ServiceProviderContract;
use Jiannei\LaravelCrawler\Kernel;
use Jiannei\LaravelCrawler\Services\PluginService;

class PluginServiceProvider implements ServiceProviderContract
{
    public function register(Kernel $kernel)
    {
        $kernel->bind('use',function ($plugins,...$opt){
            return PluginService::install($this,$plugins,...$opt);
        });
    }

}