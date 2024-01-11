<?php

/*
 * This file is part of the jiannei/laravel-crawler.
 *
 * (c) jiannei <jiannei@sinan.fun>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Jiannei\LaravelCrawler\Tests;

use Jiannei\LaravelCrawler\Providers\LaravelServiceProvider;

abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            LaravelServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app)
    {
        $app->setBasePath(__DIR__);

        $app['config']->set('cache.default', 'file');
        $app['config']->set('cache.stores.file.path', storage_path('framework/cache/data'));
        $app['config']->set('logging.channels.crawler.path', storage_path('logs/http.log'));
        $app['config']->set('crawler.source.default', 'file');
        $app['config']->set('crawler.source.channels.file', resource_path('crawler.json'));
    }
}
