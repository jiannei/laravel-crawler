<?php

namespace Jiannei\LaravelCrawler\Tests;

use Jiannei\LaravelCrawler\Support\Facades\Crawler;

class FetchTest extends TestCase
{
    public function testFetch()
    {
        $crawler = Crawler::fetch('https://www.ithome.com/html/discovery/358585.htm');

        $title = $crawler->filter('h1')->text();
        $author = $crawler->filter('#author_baidu>strong')->text();
        $content = $crawler->filter('.post_content')->html();

        $this->assertEquals('巴基斯坦一城镇温度达50.2度：创下全球4月历史温度新高',$title);
        $this->assertEquals('白猫',$author);
    }
}