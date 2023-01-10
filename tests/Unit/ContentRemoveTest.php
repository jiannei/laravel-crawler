<?php

namespace Jiannei\LaravelCrawler\Tests\Unit;

use Jiannei\LaravelCrawler\Support\Facades\Crawler;
use Jiannei\LaravelCrawler\Tests\TestCase;

class ContentRemoveTest extends TestCase
{
    protected string $html = <<<STR
    <div id="content">

        <span class="tt">作者：xxx</span>

        这是正文内容段落1.....

        <span>这是正文内容段落2</span>

        <p>这是正文内容段落3......</p>

        <span>这是广告</span>
        <p>这是版权声明！</p>
    </div>
STR;

    public function testSingleRemove()
    {
        $crawler = Crawler::new($this->html);

        $html = $crawler->filter('#content')->remove(['.tt','span:last','p:last']);

        $expected = <<<STR
这是正文内容段落1.....

        <span>这是正文内容段落2</span>

        <p>这是正文内容段落3......</p>
STR;

        $this->assertEquals($expected,trim($html));
    }

}