<?php

/*
 * This file is part of the jiannei/laravel-crawler.
 *
 * (c) jiannei <jiannei@sinan.fun>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Jiannei\LaravelCrawler\Providers;

use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Support\ServiceProvider;
use Jiannei\LaravelCrawler\Console\CrawlerRecord;
use Jiannei\LaravelCrawler\Console\CrawlerServer;
use Jiannei\LaravelCrawler\Console\CrawlerTask;
use Jiannei\LaravelCrawler\Listeners\ConnectionFailedListener;
use Jiannei\LaravelCrawler\Listeners\RequestSendingListener;
use Jiannei\LaravelCrawler\Listeners\ResponseReceivedListener;

class LaravelServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(dirname(__DIR__, 2).'/config/crawler.php', 'crawler');

        $this->app['config']->set('logging.channels.crawler', $this->app['config']->get('crawler.log'));
    }

    public function boot()
    {
        if ($this->app['config']->get('crawler.debug', false)) {
            $this->app['events']->listen(ConnectionFailed::class, ConnectionFailedListener::class);
            $this->app['events']->listen(RequestSending::class, RequestSendingListener::class);
            $this->app['events']->listen(ResponseReceived::class, ResponseReceivedListener::class);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([CrawlerServer::class, CrawlerTask::class, CrawlerRecord::class]);
            $this->setupPublishes();
        }

        $consume = $this->app['config']->get('crawler.consume.service');
        if (class_exists($consume)) {
            $this->app->bind(\Jiannei\LaravelCrawler\Contracts\ConsumeService::class, $consume);
        }
    }

    protected function setupPublishes()
    {
        $this->publishes([
            dirname(__DIR__, 2).'/config/crawler.php' => config_path('crawler.php'),
            dirname(__DIR__, 2).'/database/migrations/create_crawl_tasks_table.php.stub' => database_path('migrations/'.date('Y_m_d_His').'_create_crawl_tasks_table.php'),
            dirname(__DIR__, 2).'/database/migrations/create_crawl_records_table.php.stub' => database_path('migrations/'.date('Y_m_d_His').'_create_crawl_records_table.php'),
            dirname(__DIR__, 2).'/resources/' => resource_path(),
        ], 'crawler');
    }
}
