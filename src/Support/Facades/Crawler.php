<?php

namespace Jiannei\LaravelCrawler\Support\Facades;

use Illuminate\Support\Facades\Facade as IlluminateFacade;
class Crawler extends IlluminateFacade
{
    protected static function getFacadeAccessor()
    {
        return \Jiannei\LaravelCrawler\Crawler::class;
    }
}