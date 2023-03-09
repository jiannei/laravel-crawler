<?php

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
