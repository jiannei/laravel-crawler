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

use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Support\ServiceProvider;
use Jiannei\LaravelCrawler\Console\CrawlerRecordConsume;
use Jiannei\LaravelCrawler\Console\CrawlerTaskRun;
use Jiannei\LaravelCrawler\Console\CrawlerServer;
use Jiannei\LaravelCrawler\Console\CrawlerTaskSync;
use Jiannei\LaravelCrawler\Listeners\ConnectionFailedListener;
use Jiannei\LaravelCrawler\Listeners\RequestSendingListener;
use Jiannei\LaravelCrawler\Listeners\ResponseReceivedListener;

class LaravelServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->setupConfig();
    }

    public function boot()
    {
        if ($this->app['config']->get('crawler.debug', false)) {
            $this->app['events']->listen(ConnectionFailed::class, ConnectionFailedListener::class);
            $this->app['events']->listen(RequestSending::class, RequestSendingListener::class);
            $this->app['events']->listen(ResponseReceived::class, ResponseReceivedListener::class);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([CrawlerServer::class, CrawlerTaskRun::class,CrawlerTaskSync::class,CrawlerRecordConsume::class]);
            $this->setupMigration();
        }
    }

    protected function setupConfig()
    {
        $path = dirname(__DIR__, 2).'/config/crawler.php';

        if ($this->app->runningInConsole()) {
            $this->publishes([$path => config_path('crawler.php')], 'crawler');

            $this->publishes([
                dirname(__DIR__, 2).'/storage' => storage_path(),
            ], 'crawler');
        }

        $this->mergeConfigFrom($path, 'crawler');

        $this->app['config']->set('logging.channels.crawler', $this->app['config']->get('crawler.log'));
    }

    protected function setupMigration(): void
    {
        $this->publishes([
            __DIR__.'/../../database/migrations/create_crawl_tasks_table.php.stub' => database_path('migrations/'.date('Y_m_d_His').'_create_crawl_tasks_table.php'),
            __DIR__.'/../../database/migrations/create_crawl_records_table.php.stub' => database_path('migrations/'.date('Y_m_d_His').'_create_crawl_records_table.php'),
        ], 'migrations');
    }
}
