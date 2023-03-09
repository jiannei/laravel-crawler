<?php

namespace Jiannei\LaravelCrawler\Tests\Unit;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Stringable;
use Jiannei\LaravelCrawler\Support\Facades\Crawler;
use Jiannei\LaravelCrawler\Tests\TestCase;

class PatternTest extends TestCase
{
    public function testRules()
    {
        $baseUrl = 'https://laravel-news.com';
        $link = 'laravel-10';

        $result = Crawler::pattern([
            'url' => $baseUrl."/{$link}",
            'rules' => [
                'title' => ['h1', 'text'],
                'category.link' => [
                    'h1 + div a', 'href', null, function ($node, Stringable $val) use ($baseUrl) {
                        return $val->start($baseUrl);
                    },
                ],
                'category.name' => ['h1 + div a', 'text'],
                'author.name' => ["article div:nth-of-type(2) div:nth-of-type(2) > div:last-child a[rel='author']", 'text'],
                'author.homepage' => [
                    "article div:nth-of-type(2) div:nth-of-type(2) > div:last-child a[rel='author']", 'href', null, function ($node, Stringable $val) use ($baseUrl) {
                        return $val->start($baseUrl);
                    },
                ],
                'author.intro' => ['article div:nth-of-type(2) div:nth-of-type(2) > div:last-child p:last-child', 'text'],
                'description' => ['article div:nth-of-type(2) div:nth-of-type(1)', 'html'],
                'publishDate' => [
                    'h1 + div p', 'text', null, function ($node, $val) {
                        return Carbon::createFromTimestamp(strtotime($val))->format(CarbonInterface::DEFAULT_TO_STRING_FORMAT);
                    },
                ],
                'link' => $baseUrl."/{$link}",
            ],
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('category', $result);
        $this->assertArrayHasKey('link', $result['category']);
        $this->assertArrayHasKey('name', $result['author']);
    }

    public function testSingleGroup()
    {
        $result = Crawler::pattern([
            'url' => 'https://github.com/trending',
            'query' => ['since' => 'daily', 'spoken_language_code' => 'zh'],
            'group' => [
                'selector' => 'article',
                'rules' => [
                    'repo' => ['h1 a', 'href'],
                    'desc' => ['p', 'text'],
                    'language' => ["span[itemprop='programmingLanguage']", 'text'],
                    'stars' => ['div.f6.color-fg-muted.mt-2 > a:nth-of-type(1)', 'text'],
                    'forks' => ['div.f6.color-fg-muted.mt-2 > a:nth-of-type(2)', 'text'],
                    'added_stars' => ['div.f6.color-fg-muted.mt-2 > span.d-inline-block.float-sm-right', 'text'],
                ],
            ],
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
        $this->assertIsList($result->toArray());
    }

    public function testMultiGroups()
    {
        $result = Crawler::pattern([
            'url' => 'https://www.v2ex.com/?tab=hot',
            'group' => [
                [
                    'alias' => 'tabs',
                    'selector' => '#Tabs a',
                    'rules' => [
                        'label' => ['a', 'text'],
                        'value' => ['a', 'href'],
                    ],
                ],
                [
                    'alias' => 'nodes',
                    'selector' => '#SecondaryTabs a',
                    'rules' => [
                        'label' => ['a', 'text'],
                        'value' => ['a', 'href'],
                    ],
                ],
                [
                    'alias' => 'posts',
                    'selector' => 'div .item table',
                    'rules' => [
                        'member_avatar' => ['.avatar', 'src'],
                        'member_link' => ['strong a', 'href'],
                        'member_name' => ['strong a', 'text'],
                        'title' => ['.topic-link', 'text'],
                        'link' => ['.topic-link', 'href'],
                        'node_label' => ['.node', 'text'],
                        'node_value' => ['.node', 'href'],
                        'reply_count' => ['.count_livid', 'text'],
                    ]
                ],
            ],
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
        $this->assertArrayHasKey('tabs',$result->toArray());
        $this->assertArrayHasKey('nodes',$result->toArray());
        $this->assertArrayHasKey('posts',$result->toArray());
        $this->assertIsList($result->toArray()['posts']);
    }
}
