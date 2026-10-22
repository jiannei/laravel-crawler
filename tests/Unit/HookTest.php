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

use Jiannei\LaravelCrawler\Data\FetchResponse;
use Jiannei\LaravelCrawler\Data\RunOptions;
use Jiannei\LaravelCrawler\Exceptions\InvalidPattern;
use Jiannei\LaravelCrawler\Facades\Crawler;
use Jiannei\LaravelCrawler\Tests\TestCase;

class HookTest extends TestCase
{
    public function testBeforeRewritesTheRequest(): void
    {
        $fetcher = $this->fakeFetcher($this->response());

        $items = Crawler::pattern([
            'url' => 'https://example.test/old',
            'rules' => ['title' => ['#title', 'text']],
        ], new RunOptions(before: function (string $url, $query, array $options) {
            return ['https://example.test/new', ['from' => 'hook'], $options];
        }));

        $this->assertSame('https://example.test/new', $fetcher->requests[0]['url']);
        $this->assertSame(['from' => 'hook'], $fetcher->requests[0]['query']);
        $this->assertSame('A single page', $items->first()['title']);
    }

    public function testAfterRewritesTheResponse(): void
    {
        $this->fakeFetcher($this->response());

        $items = Crawler::pattern([
            'url' => 'https://example.test/single',
            'rules' => ['title' => ['#title', 'text']],
        ], new RunOptions(after: function (FetchResponse $response) {
            return new FetchResponse(200, '<h1 id="title">Hooked</h1>');
        }));

        $this->assertSame('Hooked', $items->first()['title']);
    }

    public function testHooksDoNotLeakIntoTheNextCall(): void
    {
        $fetcher = $this->fakeFetcher([$this->response(), $this->response()]);

        $pattern = [
            'url' => 'https://example.test/plain',
            'rules' => ['title' => ['#title', 'text']],
        ];

        Crawler::pattern($pattern, new RunOptions(before: function () {
            return ['https://example.test/hooked', [], []];
        }));

        Crawler::pattern($pattern);

        $this->assertSame('https://example.test/hooked', $fetcher->requests[0]['url']);
        $this->assertSame('https://example.test/plain', $fetcher->requests[1]['url']);
    }

    public function testBeforeMustReturnARequest(): void
    {
        $this->expectException(InvalidPattern::class);

        Crawler::pattern([
            'url' => 'https://example.test/single',
            'rules' => ['title' => ['#title', 'text']],
        ], new RunOptions(before: fn () => []));
    }

    public function testAfterMustReturnAResponse(): void
    {
        $this->fakeFetcher($this->response());

        $this->expectException(InvalidPattern::class);

        Crawler::pattern([
            'url' => 'https://example.test/single',
            'rules' => ['title' => ['#title', 'text']],
        ], new RunOptions(after: fn () => 'not a response'));
    }
}
