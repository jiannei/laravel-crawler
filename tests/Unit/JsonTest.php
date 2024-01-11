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
use Illuminate\Support\Str;
use Jiannei\LaravelCrawler\Support\Facades\Crawler;
use Jiannei\LaravelCrawler\Tests\TestCase;

class JsonTest extends TestCase
{
    public function testJson()
    {
        $result = Crawler::json('github:trending');

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertIsArray($result->toArray());
        $this->assertTrue(Arr::isList($result->toArray()));
    }

    public function testRss()
    {
        $result = Crawler::json('sspai');

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertIsArray($result->toArray());
        $this->assertTrue(Arr::isList($result->toArray()['items']));
    }

    public function testBefore()
    {
        $result = Crawler::before(function ($url, $query, $options) {
            $url = Str::of($url)->replace(':category', 'all')->value();

            return [$url, $query, $options];
        })->json('gitee');

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertIsArray($result->toArray());
        $this->assertArrayHasKey('categories', $result->toArray());
        $this->assertArrayHasKey('repos', $result->toArray());
        $this->assertArrayHasKey('daily', $result->toArray());
        $this->assertArrayHasKey('weekly', $result->toArray());
        $this->assertTrue(Arr::isList($result->get('daily')->all()));
    }
}
