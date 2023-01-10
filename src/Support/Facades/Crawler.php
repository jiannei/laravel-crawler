<?php

namespace Jiannei\LaravelCrawler\Support\Facades;

use Illuminate\Support\Facades\Facade as IlluminateFacade;

/**
 * @method static \Symfony\Component\DomCrawler\Crawler html(\DOMNodeList|\DOMNode|array|string $node = null, string $uri = null, string $baseHref = null)
 */
class Crawler extends IlluminateFacade
{
    protected static function getFacadeAccessor()
    {
        return \Jiannei\LaravelCrawler\Crawler::class;
    }
}