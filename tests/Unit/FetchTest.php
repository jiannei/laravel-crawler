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
use Jiannei\LaravelCrawler\Exceptions\FetchFailed;
use Jiannei\LaravelCrawler\Facades\Crawler;
use Jiannei\LaravelCrawler\Parsing\Page;
use Jiannei\LaravelCrawler\Tests\TestCase;

class FetchTest extends TestCase
{
    public function testFetchReturnsAPage(): void
    {
        $fetcher = $this->fakeFetcher($this->response());

        $page = Crawler::fetch('https://example.test/single', ['page' => '2'], ['timeout' => 5]);

        $this->assertInstanceOf(Page::class, $page);
        $this->assertSame('A single page', $page->filter('#title')->text());
        $this->assertSame('https://example.test/single', $fetcher->requests[0]['url']);
        $this->assertSame(['page' => '2'], $fetcher->requests[0]['query']);
        $this->assertSame(['timeout' => 5], $fetcher->requests[0]['options']);
    }

    public function testNonSuccessfulFetchCarriesTheResponse(): void
    {
        $this->fakeFetcher(new FetchResponse(404, 'missing'));

        try {
            Crawler::fetch('https://example.test/missing');
            $this->fail('A non-successful response should not become a Page.');
        } catch (FetchFailed $failure) {
            $this->assertSame('https://example.test/missing', $failure->url);
            $this->assertSame(404, $failure->status);
            $this->assertSame('missing', $failure->body);
        }
    }
}
