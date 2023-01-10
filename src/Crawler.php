<?php

namespace Jiannei\LaravelCrawler;

use Symfony\Component\DomCrawler\Crawler as SymfonyCrawler;

class Crawler
{
    public function html(\DOMNodeList|\DOMNode|array|string $node = null, string $uri = null, string $baseHref = null)
    {
        return new \Symfony\Component\DomCrawler\Crawler(...func_get_args());
    }
}