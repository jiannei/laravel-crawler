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

use Jiannei\LaravelCrawler\Data\RunOptions;
use Jiannei\LaravelCrawler\Exceptions\InvalidPattern;
use Jiannei\LaravelCrawler\Facades\Crawler;
use Jiannei\LaravelCrawler\Tests\TestCase;

class JsonTest extends TestCase
{
    public function testStoredGroupPattern(): void
    {
        $fetcher = $this->fakeFetcher($this->response());

        $items = Crawler::json('fixture:list', new RunOptions(query: ['since' => 'daily']));

        $this->assertCount(2, $items);
        $this->assertSame('Alpha', $items->first()['title']);
        $this->assertSame(['page' => '1', 'since' => 'daily'], $fetcher->requests[0]['query']);
        $this->assertSame('text/html', $fetcher->requests[0]['options']['headers']['Accept']);
    }

    public function testStoredUngroupedPattern(): void
    {
        $this->fakeFetcher($this->response());

        $items = Crawler::json('fixture:single');

        $this->assertCount(1, $items);
        $this->assertSame('A single page', $items->first()['title']);
    }

    public function testStoredRssPattern(): void
    {
        $fetcher = $this->fakeFetcher($this->response('feed.xml'));

        $result = Crawler::json('fixture:rss', new RunOptions(query: ['limit' => 10]));

        $this->assertTrue($result->has('channel'));
        $this->assertTrue($result->has('items'));
        $this->assertSame('Example feed', $result->get('channel')['title']);
        $this->assertCount(2, $result->get('items'));
        $this->assertSame(['limit' => 10], $fetcher->requests[0]['query']);
    }

    public function testUnknownKeyIsInvalid(): void
    {
        $this->expectException(InvalidPattern::class);

        Crawler::json('fixture:unknown');
    }
}
