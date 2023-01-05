<?php

namespace Jiannei\LaravelCrawler\Providers;

use Illuminate\Support\ServiceProvider;
use Jiannei\LaravelCrawler\Support\Query\phpQuery;

class LaravelServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->setupConfig();
    }

    public function boot()
    {
        phpQuery::$debug = config('crawler.debug',false);
    }

    protected function setupConfig()
    {
        $path = dirname(__DIR__, 2).'/config/crawler.php';

        if ($this->app->runningInConsole()) {
            $this->publishes([$path => config_path('crawler.php')], 'crawler');
        }

        $this->mergeConfigFrom($path, 'crawler');
    }
}