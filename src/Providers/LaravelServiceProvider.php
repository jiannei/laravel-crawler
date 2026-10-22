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

use Illuminate\Support\ServiceProvider;
use Jiannei\LaravelCrawler\Actions\PatternRun;
use Jiannei\LaravelCrawler\Chrome\ChromeBrowser;
use Jiannei\LaravelCrawler\Chrome\ChromeDriverFactory;
use Jiannei\LaravelCrawler\Chrome\ChromeSettings;
use Jiannei\LaravelCrawler\Chrome\RemoteChromeDriverFactory;
use Jiannei\LaravelCrawler\Console\CrawlerRecord;
use Jiannei\LaravelCrawler\Console\CrawlerServer;
use Jiannei\LaravelCrawler\Console\CrawlerTask;
use Jiannei\LaravelCrawler\Contracts\Browser;
use Jiannei\LaravelCrawler\Contracts\ConsumeService;
use Jiannei\LaravelCrawler\Contracts\Fetcher;
use Jiannei\LaravelCrawler\Http\DebugFetcher;
use Jiannei\LaravelCrawler\Http\HttpFetcher;
use Jiannei\LaravelCrawler\Sources\PatternSourceManager;

class LaravelServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(dirname(__DIR__, 2).'/config/crawler.php', 'crawler');

        $this->app['config']->set('logging.channels.crawler', $this->app['config']->get('crawler.log'));

        $this->app->singleton(Fetcher::class, function ($app) {
            $fetcher = new HttpFetcher($app['config']->get('crawler.guzzle.options', []));

            if (!$app['config']->get('crawler.fetch.debug', false)) {
                return $fetcher;
            }

            return new DebugFetcher($fetcher, $app['log']->channel('crawler'));
        });

        $this->app->singleton(PatternRun::class);

        $this->app->singleton(PatternSourceManager::class, function ($app) {
            return new PatternSourceManager($app, $app['config']->get('crawler.source.channels', []));
        });

        $this->app->singleton(ChromeSettings::class, function ($app) {
            return ChromeSettings::fromConfig($app['config']->get('crawler.chrome', []));
        });

        $this->app->singleton(ChromeDriverFactory::class, RemoteChromeDriverFactory::class);

        $this->app->singleton(Browser::class, function ($app) {
            return new ChromeBrowser(
                $app->make(ChromeSettings::class),
                $app->make(ChromeDriverFactory::class),
            );
        });
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([CrawlerServer::class, CrawlerTask::class, CrawlerRecord::class]);
            $this->setupPublishes();
        }

        $consume = $this->app['config']->get('crawler.consume.service');

        if (null !== $consume && '' !== $consume) {
            if (!is_string($consume) || !class_exists($consume) || !is_subclass_of($consume, ConsumeService::class)) {
                $name = is_string($consume) ? $consume : get_debug_type($consume);

                throw new \InvalidArgumentException("The configured consume service [$name] must implement ".ConsumeService::class.'.');
            }

            $this->app->bind(ConsumeService::class, $consume);
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
