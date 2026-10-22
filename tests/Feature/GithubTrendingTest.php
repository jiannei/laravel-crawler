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

use Jiannei\LaravelCrawler\Facades\Crawler;
use Jiannei\LaravelCrawler\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('integration')]
class GithubTrendingTest extends TestCase
{
    public function testTrendingRepos(): void
    {
        $url = 'https://github.com/trending';
        $page = Crawler::fetch($url, ['spoken_language_code' => 'zh', 'since' => 'daily']);

        $rules = [
            'repo' => ['h1 a', 'href'],
            'desc' => ['p', 'text'],
            'language' => ["span[itemprop='programmingLanguage']", 'text'],
            'stars' => ['div.f6.color-fg-muted.mt-2 > a:nth-of-type(1)', 'text'],
            'forks' => ['div.f6.color-fg-muted.mt-2 > a:nth-of-type(2)', 'text'],
            'added_stars' => ['div.f6.color-fg-muted.mt-2 > span.d-inline-block.float-sm-right', 'text'],
        ];

        $trending = $page->filter('article')->parse($rules)->all();

        $this->assertIsArray($trending);
        $this->assertNotEmpty($trending);
    }
}
