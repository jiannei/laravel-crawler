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

use Illuminate\Support\Collection;
use Jiannei\LaravelCrawler\Data\FetchResponse;
use Jiannei\LaravelCrawler\Exceptions\FetchFailed;
use Jiannei\LaravelCrawler\Exceptions\InvalidPattern;
use Jiannei\LaravelCrawler\Facades\Crawler;
use Jiannei\LaravelCrawler\Tests\TestCase;

class PatternTest extends TestCase
{
    public function testSingleGroupReturnsOneItemPerMatch(): void
    {
        $this->fakeFetcher($this->response());

        $items = Crawler::pattern([
            'url' => 'https://example.test/list',
            'group' => [
                'selector' => '.item',
                'rules' => [
                    'title' => ['h2 a', 'text'],
                    'link' => ['h2 a', 'href'],
                    'author' => ['.author', 'text'],
                ],
            ],
        ]);

        $this->assertInstanceOf(Collection::class, $items);
        $this->assertCount(2, $items);
        $this->assertSame('Alpha', $items->first()['title']);
        $this->assertSame('/a', $items->first()['link']);
        $this->assertSame('Grace', $items->last()['author']);
    }

    public function testUngroupedPatternReturnsOneItem(): void
    {
        $this->fakeFetcher($this->response());

        $items = Crawler::pattern([
            'url' => 'https://example.test/single',
            'rules' => [
                'title' => ['#title', 'text'],
                'author' => ['#author', 'text'],
                'body' => ['.body', 'html'],
            ],
        ]);

        $this->assertCount(1, $items);
        $this->assertSame('A single page', $items->first()['title']);
        $this->assertSame('Ada', $items->first()['author']);
        $this->assertStringContainsString('<b>world</b>', $items->first()['body']);
    }

    public function testMultipleGroupsKeepTheirAliases(): void
    {
        $this->fakeFetcher($this->response());

        $items = Crawler::pattern([
            'url' => 'https://example.test/page',
            'group' => [
                [
                    'alias' => 'list',
                    'selector' => '.item',
                    'rules' => ['title' => ['h2 a', 'text']],
                ],
                [
                    'alias' => 'single',
                    'selector' => '#single',
                    'rules' => ['title' => ['#title', 'text']],
                ],
            ],
        ]);

        $this->assertTrue($items->has('list'));
        $this->assertCount(2, $items->get('list'));
        $this->assertSame('A single page', $items->get('single')->first()['title']);
    }

    public function testPositionAndCallbackRules(): void
    {
        $this->fakeFetcher($this->response());

        $items = Crawler::pattern([
            'url' => 'https://example.test/list',
            'group' => [
                'selector' => '.item',
                'rules' => [
                    'last_desc' => ['.desc', 'text', 'last', function ($node, $value) {
                        return $value->upper();
                    }],
                ],
            ],
        ]);

        $this->assertSame('SECOND', (string) $items->last()['last_desc']);
    }

    public function testMissingFieldBecomesNull(): void
    {
        $this->fakeFetcher($this->response());

        $items = Crawler::pattern([
            'url' => 'https://example.test/single',
            'rules' => [
                'missing' => ['.does-not-exist', 'text'],
            ],
        ]);

        $this->assertCount(1, $items);
        $this->assertNull($items->first()['missing']);
    }

    public function testPageParseReturnsACollection(): void
    {
        $page = Crawler::new('<div class="row"><span>A</span><span>B</span></div>');

        $items = $page->filter('.row')->parse([
            'first' => ['span', 'text'],
            'second' => ['span', 'text', 'last'],
        ]);

        $this->assertInstanceOf(Collection::class, $items);
        $this->assertSame('A', $items->first()['first']);
        $this->assertSame('B', $items->first()['second']);
    }

    public function testEmptySelectionReturnsAnEmptyCollection(): void
    {
        $items = Crawler::new('<div></div>')
            ->filter('.missing')
            ->parse(['text' => ['span', 'text']]);

        $this->assertTrue($items->isEmpty());
    }

    public function testPatternWithoutRulesIsInvalid(): void
    {
        $this->expectException(InvalidPattern::class);

        Crawler::pattern([
            'url' => 'https://example.test/single',
            'rules' => [],
        ]);
    }

    public function testInvalidRuleIsRejected(): void
    {
        $this->fakeFetcher($this->response());

        $this->expectException(InvalidPattern::class);

        Crawler::pattern([
            'url' => 'https://example.test/single',
            'rules' => ['title' => ['h1']],
        ]);
    }

    public function testNonSuccessfulResponseIsARejectedCrawl(): void
    {
        $this->fakeFetcher(new FetchResponse(503, 'down'));

        $this->expectException(FetchFailed::class);

        Crawler::pattern([
            'url' => 'https://example.test/single',
            'rules' => ['title' => ['#title', 'text']],
        ]);
    }
}
