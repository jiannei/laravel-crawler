<?php

/*
 * This file is part of the jiannei/laravel-crawler.
 *
 * (c) jiannei <jiannei@sinan.fun>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Jiannei\LaravelCrawler\Tests\Unit;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Jiannei\LaravelCrawler\Support\Facades\Crawler;
use Jiannei\LaravelCrawler\Tests\TestCase;

class RssTest extends TestCase
{
    public function testRss()
    {
        $result = Crawler::rss('https://packagist.org/feeds/packages.rss');

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertTrue($result->has('channel'));
        $this->assertTrue($result->has('items'));
        $this->assertTrue(Arr::isList($result->get('items')));
    }
}
