<?php

namespace Jiannei\LaravelCrawler\Tests;

use Illuminate\Support\Str;
use Jiannei\LaravelCrawler\Support\Facades\Crawler;

class FilterTest extends TestCase
{
    protected string $html = <<<HTML
<div id="one">
    <div class="two">
        <a href="http://querylist.cc">QueryList官网</a>
        <img src="http://querylist.com/1.jpg" alt="这是图片" abc="这是一个自定义属性">
        <img class="second_pic" src="http://querylist.com/2.jpg" alt="这是图片2">
        <a href="http://doc.querylist.cc">QueryList文档</a>
    </div>
    <span>其它的<b>一些</b>文本</span>
    <span>另外<b>一些</b>文本</span>
</div>
HTML;

    public function testFilterFirstDomAttr()
    {
        $crawler = Crawler::html($this->html);

        $img = [];
        $img[] = $crawler->filter('img')->attr('src');
        $img[] = $crawler->filter('img')->eq(0)->attr('src');
        $img[] = $crawler->filter('img')->image()->getUri();

        $alt = $crawler->filter('img')->attr('alt');
        $abc = $crawler->filter('img')->attr('abc');

        $this->assertCount(1, array_unique($img));
        $this->assertEquals('这是图片', $alt);
        $this->assertEquals('这是一个自定义属性', $abc);
    }

    public function testFilterSecondDomAttr()
    {
        $crawler = Crawler::html($this->html);

        $img = [];
        $img[] = $crawler->filter('img')->eq(1)->attr('alt');
        $img[] = $crawler->filter('.second_pic')->attr('alt');

        $this->assertCount(1, array_unique($img));
    }

    public function testFilterDomMultiAttr()
    {
        $crawler = Crawler::html($this->html);

        $imgAttrs = $crawler->filter('img')->eq(0)->extract(['src', 'alt', 'abc']);
        $linkAttrs = $crawler->filter('a')->eq(1)->extract(['href']);

        $this->assertCount(3, $imgAttrs[0]);
        $this->assertCount(1, $linkAttrs);
    }

    public function testGetHtml()
    {
        $crawler = Crawler::html($this->html);

        $html = $crawler->filter('#one > .two')->html();

        $this->assertTrue(Str::contains($html, ['QueryList官网', 'QueryList文档']));
    }

    public function testGetText()
    {
        $crawler = Crawler::html($this->html);

        $text = $crawler->filter('.two')->text();

        $this->assertEquals('QueryList官网 QueryList文档', $text);
    }

    public function testGetAllAlt()
    {
        $crawler = Crawler::html($this->html);

        $imgAlt = $crawler->filter('.two img')->each(function (\Symfony\Component\DomCrawler\Crawler $node, $i) {
            return $node->attr('alt');
        });

        $this->assertEquals(["这是图片", "这是图片2"], $imgAlt);
    }

    public function testGetAllHtml()
    {
        $crawler = Crawler::html($this->html);

        $htmls = $crawler->filter('#one span')->each(function (\Symfony\Component\DomCrawler\Crawler $node) {
            return $node->html();
        });

        $this->assertEquals(["其它的<b>一些</b>文本","另外<b>一些</b>文本"],$htmls);
    }

    public function testGetAllText()
    {
        $crawler = Crawler::html($this->html);

        $texts = $crawler->filter('.two a')->each(function (\Symfony\Component\DomCrawler\Crawler $node) {
            return $node->text();
        });

        $this->assertEquals(['QueryList官网','QueryList文档'],$texts);
    }
}