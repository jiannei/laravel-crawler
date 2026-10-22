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

use Illuminate\Support\Arr;
use Jiannei\LaravelCrawler\Facades\Crawler;
use Jiannei\LaravelCrawler\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('integration')]
class RssIntegrationTest extends TestCase
{
    public function testPackagistFeed(): void
    {
        $result = Crawler::rss('https://packagist.org/feeds/packages.rss');

        $this->assertTrue($result->has('channel'));
        $this->assertTrue($result->has('items'));
        $this->assertTrue(Arr::isList($result->get('items')));
    }
}
