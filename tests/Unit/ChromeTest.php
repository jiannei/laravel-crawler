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

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Jiannei\LaravelCrawler\Contracts\Browser;
use Jiannei\LaravelCrawler\Facades\Crawler;
use Jiannei\LaravelCrawler\Tests\TestCase;

class ChromeTest extends TestCase
{
    public function testChromeWrapsRenderedHtmlInAPage(): void
    {
        $browser = new class implements Browser {
            public ?string $requestedUrl = null;

            public function html(string $url, WebDriverExpectedCondition $condition): string
            {
                $this->requestedUrl = $url;

                return '<html><body><h1>Rendered</h1></body></html>';
            }
        };

        $this->app->instance(Browser::class, $browser);

        $page = Crawler::chrome(
            'https://example.test',
            WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('h1')),
        );

        $this->assertSame('https://example.test', $browser->requestedUrl);
        $this->assertSame('Rendered', $page->filter('h1')->text());
    }
}
