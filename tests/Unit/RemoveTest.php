<?php

/*
 * This file is part of the jiannei/laravel-crawler.
 *
 * (c) jiannei <longjian.huang@foxmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Jiannei\LaravelCrawler\Tests\Unit;

use Jiannei\LaravelCrawler\Support\Facades\Crawler;
use Jiannei\LaravelCrawler\Tests\TestCase;

class RemoveTest extends TestCase
{
    protected string $html = <<<STR
    <div id="content">

        <span class="tt">作者：xxx</span>

        这是正文内容段落1.....

        <span>这是正文内容段落2</span>

        <p>这是正文内容段落3......</p>

        <a href="http://querylist.cc">QueryList官网</a>

        <span>这是广告</span>
        <p>这是版权声明！</p>
    </div>
STR;

    public function testRemoveHtml()
    {
        $crawler = Crawler::new($this->html);

        $html = $crawler->filter('#content')->remove(['.tt', 'span;last', 'p;last', 'a']);

        $expected = <<<STR
这是正文内容段落1.....

        <span>这是正文内容段落2</span>

        <p>这是正文内容段落3......</p>
STR;

        $this->assertEquals($expected, trim($html));
    }
}
