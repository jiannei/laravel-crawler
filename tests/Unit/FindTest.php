<?php

namespace Jiannei\LaravelCrawler\Tests\Unit;

use Jiannei\LaravelCrawler\QueryList;
use Jiannei\LaravelCrawler\Tests\TestCase;

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

    protected $ql;

    public function setUp(): void
    {
        $this->ql = QueryList::html($this->html);
    }

    public function testFindFirstDomAttr()
    {
        $img = [];
        $img[] = $this->ql->find('img')->attr('src');
        $img[] = $this->ql->find('img')->src;
        $img[] = $this->ql->find('img:eq(0)')->src;
        $img[] = $this->ql->find('img')->eq(0)->src;

        $alt = $this->ql->find('img')->alt;
        $abc = $this->ql->find('img')->abc;

        $this->assertCount(1, array_unique($img));
        $this->assertEquals($alt, '这是图片');
        $this->assertEquals($abc, '这是一个自定义属性');

    }


    public function testFindSecondDomAttr()
    {

        $img2 = [];
        $img2[] = $this->ql->find('img')->eq(1)->alt;
        $img2[] = $this->ql->find('img:eq(1)')->alt;
        $img2[] = $this->ql->find('.second_pic')->alt;

        $this->assertCount(1, array_unique($img2));

    }

    public function testFindDomAllAttr()
    {
        $imgAttr = $this->ql->find('img:eq(0)')->attr('*');
        $linkAttr = $this->ql->find('a:eq(1)')->attr('*');
        $this->assertCount(3, $imgAttr);
        $this->assertCount(1, $linkAttr);
    }
}