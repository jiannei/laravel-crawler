<?php

/*
 * This file is part of the jiannei/laravel-crawler.
 *
 * (c) jiannei <longjian.huang@foxmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Jiannei\LaravelCrawler\Tests;

use Jiannei\LaravelCrawler\QueryList;

class FindTest extends TestCase
{
    protected $html = <<<STR
<div id="one">
    <div class="two">
        <a href="http://querylist.cc">QueryList官网</a>
        <img src="http://querylist.com/1.jpg" alt="这是图片" abc="这是一个自定义属性">
        <img class="second_pic" src="http://querylist.com/2.jpg" alt="这是图片2">
        <a href="http://doc.querylist.cc">QueryList文档</a>
    </div>
    <span>其它的<b>一些</b>文本</span>
</div>
STR;

    public function testFindFirstDomAttr()
    {
        $ql = QueryList::setHtml($this->html);

        $img = [];
        $img[] = $ql->find('img')->attr('src');
        $img[] = $ql->find('img')->src;
        $img[] = $ql->find('img:eq(0)')->src;
        $img[] = $ql->find('img')->eq(0)->src;

        $alt = $ql->find('img')->alt;
        $abc = $ql->find('img')->abc;

        $this->assertCount(1, array_unique($img));
        $this->assertEquals($alt, '这是图片');
        $this->assertEquals($abc, '这是一个自定义属性');
    }

    public function testFindSecondDomAttr()
    {
        $ql = QueryList::setHtml($this->html);

        $img2 = [];
        $img2[] = $ql->find('img')->eq(1)->alt;
        $img2[] = $ql->find('img:eq(1)')->alt;
        $img2[] = $ql->find('.second_pic')->alt;

        $this->assertCount(1, array_unique($img2));
    }

    public function testFindDomAllAttr()
    {
        $ql = QueryList::setHtml($this->html);

        $imgAttr = $ql->find('img:eq(0)')->attr('*');
        $linkAttr = $ql->find('a:eq(1)')->attr('*');
        $this->assertCount(3, $imgAttr);
        $this->assertCount(1, $linkAttr);
    }
}
