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
use Jiannei\LaravelCrawler\Data\RunOptions;
use Jiannei\LaravelCrawler\Facades\Crawler;
use Jiannei\LaravelCrawler\Tests\TestCase;

class RssTest extends TestCase
{
    public function testRssShapesChannelAndItems(): void
    {
        $this->fakeFetcher($this->response('feed.xml'));

        $result = Crawler::rss('https://example.test/feed.xml');

        $this->assertTrue($result->has('channel'));
        $this->assertTrue($result->has('items'));
        $this->assertSame('Example feed', $result->get('channel')['title']);
        $this->assertTrue(Arr::isList($result->get('items')));
        $this->assertCount(2, $result->get('items'));
        $this->assertSame('First post', $result->get('items')[0]['title']);
    }

    public function testRssHonoursRunOptions(): void
    {
        $fetcher = $this->fakeFetcher($this->response('feed.xml'));

        Crawler::rss('https://example.test/feed.xml', [], new RunOptions(query: ['limit' => 5]));

        $this->assertSame(['limit' => 5], $fetcher->requests[0]['query']);
    }
}
