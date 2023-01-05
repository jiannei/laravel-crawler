<?php

/*
 * This file is part of the jiannei/laravel-crawler.
 *
 * (c) jiannei <longjian.huang@foxmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

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
        $elements = $this->document->find($selector);

        return new Elements($elements);
    }
}
