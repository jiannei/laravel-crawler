<?php

namespace Jiannei\LaravelCrawler\Tests\Unit;


use Jiannei\LaravelCrawler\QueryList;
use Jiannei\LaravelCrawler\Tests\TestCase;

class MethodTest extends TestCase
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

    public function testPipe()
    {
        $html = $this->html;
        $qlHtml = QueryList::pipe(function(QueryList $ql) use($html){
            $ql->setHtml($html);
            return $ql;
        })->getHtml(false);
        $this->assertEquals($html,$qlHtml);
    }
}