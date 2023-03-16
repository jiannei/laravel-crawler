<?php

/*
 * This file is part of the jiannei/laravel-crawler.
 *
 * (c) jiannei <longjian.huang@foxmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Jiannei\LaravelCrawler\Support\Facades;

use Facebook\WebDriver\WebDriverExpectedCondition;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade as IlluminateFacade;

/**
 * @method static PendingRequest                  client(array $options = [])
 * @method static \Jiannei\LaravelCrawler\Crawler new(\DOMNodeList|\DOMNode|array|string $node = null, string $uri = null, string $baseHref = null)
 * @method static \Jiannei\LaravelCrawler\Crawler fetch(string $url, array|string|null $query = null,array $options = [])
 * @method static array|Collection                pattern(array $pattern)
 * @method static array|Collection                json(string $key,array $query = [])
 * @method static Collection                      rss(string $url)
 * @method static \Jiannei\LaravelCrawler\Crawler chrome(string $url, WebDriverExpectedCondition $condition)
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
