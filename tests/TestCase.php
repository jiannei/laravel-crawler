<?php

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
        $app->useStoragePath(__DIR__.DIRECTORY_SEPARATOR.'storage');

        $app['config']->set('cache.default', 'file');
        $app['config']->set('cache.stores.file.path', storage_path('framework/cache/data'));

        $app['config']->set('logging.channels.single.path', storage_path('logs/crawler.log'));

    }
}
