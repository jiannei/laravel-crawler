<?php

namespace Jiannei\LaravelCrawler\Tests\Unit;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Jiannei\LaravelCrawler\Support\Facades\Crawler;
use Jiannei\LaravelCrawler\Tests\TestCase;

class JsonTest extends TestCase
{
    public function testJson()
    {
        $result = Crawler::json('https://github.com/trending');

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertIsArray($result->toArray());
        $this->assertTrue(Arr::isList($result->toArray()));
    }
}
