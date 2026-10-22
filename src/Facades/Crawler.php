<?php

/*
 * This file is part of the jiannei/laravel-crawler.
 *
 * (c) jiannei <jiannei@sinan.fun>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Jiannei\LaravelCrawler\Facades;

use Facebook\WebDriver\WebDriverExpectedCondition;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade as IlluminateFacade;

/**
 * @method static \Jiannei\LaravelCrawler\Parsing\Page new(\DOMNodeList|\DOMNode|array|string $node = null, string $uri = null, string $baseHref = null)
 * @method static \Jiannei\LaravelCrawler\Parsing\Page fetch(string $url, array|string|null $query = null, array $options = [])
 * @method static Collection                           pattern(array $pattern, \Jiannei\LaravelCrawler\Data\RunOptions $options = new \Jiannei\LaravelCrawler\Data\RunOptions())
 * @method static Collection                           json(string $key, \Jiannei\LaravelCrawler\Data\RunOptions $options = new \Jiannei\LaravelCrawler\Data\RunOptions())
 * @method static Collection                           rss(string $url, array $pattern = [], \Jiannei\LaravelCrawler\Data\RunOptions $options = new \Jiannei\LaravelCrawler\Data\RunOptions())
 * @method static \Jiannei\LaravelCrawler\Parsing\Page chrome(string $url, WebDriverExpectedCondition $condition)
 *
 * @see \Jiannei\LaravelCrawler\Crawler
 */
class Crawler extends IlluminateFacade
{
    protected static function getFacadeAccessor()
    {
        return \Jiannei\LaravelCrawler\Crawler::class;
    }
}
