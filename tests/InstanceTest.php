<?php

namespace Jiannei\LaravelCrawler\Tests;


use Jiannei\LaravelCrawler\QueryList;

class InstanceTest extends TestCase
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

    public function testSingletonInstanceMode()
    {
        $ql = QueryList::getInstance()->html($this->html);
        $ql2 = QueryList::getInstance();
        $this->assertEquals($ql->getHtml(),$ql2->getHtml());
    }

    public function testGetNewObject()
    {
        $ql = (new QueryList())->html($this->html);
        $ql2 = (new QueryList())->html('');
        $this->assertNotEquals($ql->getHtml(),$ql2->getHtml());

        $ql = QueryList::range('')->html($this->html);
        $ql2 = QueryList::range('')->html('');
        $this->assertNotEquals($ql->getHtml(),$ql2->getHtml());
    }
}