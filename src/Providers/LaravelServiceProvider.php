<?php

/*
 * This file is part of the jiannei/laravel-crawler.
 *
 * (c) jiannei <longjian.huang@foxmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Jiannei\LaravelCrawler\Providers;

use Illuminate\Support\ServiceProvider;

class LaravelServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->setupConfig();
    }

    public function boot()
    {

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
