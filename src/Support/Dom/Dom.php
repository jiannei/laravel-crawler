<?php

namespace Jiannei\LaravelCrawler\Support\Dom;

use Jiannei\LaravelCrawler\Support\Query\phpQueryObject;

class Dom
{

    protected $document;

    /**
     * Dom constructor.
     */
    public function __construct(phpQueryObject $document)
    {
        $this->document = $document;
    }

    public function find($selector)
    {
        $elements =  $this->document->find($selector);
        return new Elements($elements);
    }
}