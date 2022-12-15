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
        $app['config']->set('cache.default', 'file');

        $app['config']->set('cache.stores.file.path', __DIR__.'/cache');

    }
}
