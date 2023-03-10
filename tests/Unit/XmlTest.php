<?php

/*
 * This file is part of the jiannei/laravel-crawler.
 *
 * (c) jiannei <longjian.huang@foxmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Jiannei\LaravelCrawler\Tests\Unit;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Jiannei\LaravelCrawler\Support\Facades\Crawler;
use Jiannei\LaravelCrawler\Tests\TestCase;

class XmlTest extends TestCase
{
    public function testXml()
    {
        $crawler = Crawler::contentXml()->fetch('https://api.github.com/repos/LaravelDaily/laravel-tips/git/trees/master?recursive=1');

        $result = $crawler->group('tree')->parse([
            'path' => ['path', 'text'],
            'url' => ['url', 'text'],
        ]);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertIsArray($result->all());
        $this->assertTrue(Arr::isList($result->all()));
    }
}
