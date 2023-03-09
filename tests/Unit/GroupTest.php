<?php

namespace Jiannei\LaravelCrawler\Tests\Unit;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Jiannei\LaravelCrawler\Support\Facades\Crawler;
use Jiannei\LaravelCrawler\Tests\TestCase;

class GroupTest extends TestCase
{
    public function testParse()
    {
        $crawler = Crawler::fetch('https://packagist.org/feeds/packages.rss');

        // 单个元素 filter + parse
        $channel1 = $crawler->filter('channel')->parse([
            'title' => ['title', 'text'],
            'link' => ['link', 'text'],
            'description' => ['description', 'text'],
            'lastBuildDate' => ['pubDate', 'text'],
        ]);

        // 单个元素 group + parse
        $channel2 = $crawler->group('channel')->parse([
            'title' => ['title', 'text'],
            'link' => ['link', 'text'],
            'description' => ['description', 'text'],
            'lastBuildDate' => ['pubDate', 'text'],
        ]);

        $this->assertIsArray($channel1);
        $this->assertInstanceOf(Collection::class, $channel2);
        $this->assertEquals($channel1, $channel2->first());
        $this->assertArrayHasKey('title', $channel1);
    }

    public function testGroupParse()
    {
        $crawler = Crawler::fetch('https://packagist.org/feeds/packages.rss');

        $items = $crawler->group('channel item')->parse([
            'category' => ['category', 'text'],
            'title' => ['title', 'text'],
            'description' => ['description', 'text'],
            'link' => ['link', 'text'],
            'guid' => ['guid', 'text'],
            'pubDate' => ['pubDate', 'text'],
        ]);

        $this->assertInstanceOf(Collection::class, $items);
        $this->assertIsArray($items->all());
        $this->assertTrue(Arr::isList($items->all()));
    }
}
