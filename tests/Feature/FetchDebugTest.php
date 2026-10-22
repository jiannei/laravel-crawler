<?php

/*
 * This file is part of the jiannei/laravel-crawler.
 *
 * (c) jiannei <jiannei@sinan.fun>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Jiannei\LaravelCrawler\Tests\Feature;

use Jiannei\LaravelCrawler\Contracts\Fetcher;
use Jiannei\LaravelCrawler\Http\DebugFetcher;
use Jiannei\LaravelCrawler\Http\HttpFetcher;
use Jiannei\LaravelCrawler\Tests\TestCase;

class FetchDebugTest extends TestCase
{
    public function testItLeavesTheFetcherUnwrappedWhenDebugIsOff(): void
    {
        $this->assertInstanceOf(HttpFetcher::class, $this->app->make(Fetcher::class));
    }

    public function testItWrapsTheFetcherWhenDebugIsOn(): void
    {
        $this->app->forgetInstance(Fetcher::class);
        $this->app['config']->set('crawler.fetch.debug', true);

        $this->assertInstanceOf(DebugFetcher::class, $this->app->make(Fetcher::class));
    }
}
