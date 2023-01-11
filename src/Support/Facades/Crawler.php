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

use Illuminate\Support\Facades\Facade as IlluminateFacade;

/**
 * @method static \Jiannei\LaravelCrawler\Crawler new(\DOMNodeList|\DOMNode|array|string $node = null, string $uri = null, string $baseHref = null)
 * @method static \Jiannei\LaravelCrawler\Crawler fetch(string $url, array|string|null $query = null)
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
