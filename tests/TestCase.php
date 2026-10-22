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

use Jiannei\LaravelCrawler\Contracts\Fetcher;
use Jiannei\LaravelCrawler\Data\FetchResponse;
use Jiannei\LaravelCrawler\Providers\LaravelServiceProvider;
use Jiannei\LaravelCrawler\Tests\Support\FakeFetcher;

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
        $app['config']->set('crawler.source.channels.file.path', resource_path('fixtures.json'));

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }

    protected function fakeFetcher(FetchResponse|array $responses): FakeFetcher
    {
        $fetcher = new FakeFetcher($responses);
        $this->app->instance(Fetcher::class, $fetcher);

        return $fetcher;
    }

    protected function response(string $fixture = 'page.html', int $status = 200): FetchResponse
    {
        return new FetchResponse($status, file_get_contents(resource_path($fixture)));
    }
}
