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

class FetchTest extends TestCase
{
    public function testFetch()
    {
        $crawler = Crawler::fetch('https://www.ithome.com/html/discovery/358585.htm');

        $title = $crawler->filter('h1')->text();
        $author = $crawler->filter('#author_baidu>strong')->text();
        $content = $crawler->filter('.post_content')->html();

        $this->assertEquals('巴基斯坦一城镇温度达50.2度：创下全球4月历史温度新高', $title);
        $this->assertEquals('白猫', $author);
    }

    public function testRules()
    {
        $crawler = Crawler::fetch('https://www.ithome.com/html/discovery/358585.htm');

        $rules = [
            'title' => ['h1', 'text'],
            'author' => ['#author_baidu>strong', 'text'],
            'content' => ['.post_content', 'html'],
        ];

        // 解析文章详情
        $article = [
            'title' => $crawler->filter('h1')->text(),
            'author' => $crawler->filter('#author_baidu>strong')->text(),
            'content' => $crawler->filter('.post_content')->html(),
        ];

        // 等价于
        $article2 = $crawler->rules($rules);

        $this->assertEquals($article, $article2[0]);
    }

    public function testRulesAdvanced()
    {
        $crawler = Crawler::fetch('https://it.ithome.com/ityejie');

        $rules = [
            'title' => ['h2>a', 'text'],
            'link' => ['h2>a', 'href'],
            'img' => ['a>img', 'src'],
            'desc' => ['.m', 'text'],
        ];

        // 解析文章列表
        $articles = $crawler->filter('.bl li')->each(function ($node) {
            return [
                'title' => $node->filter('h2>a')->text(),
                'link' => $node->filter('h2>a')->attr('href'),
                'img' => $node->filter('a img')->attr('src'),
                'desc' => $node->filter('.m')->text(),
            ];
        });

        // 等价于
        $articles2 = $crawler->filter('.bl li')->rules($rules);

        $this->assertEquals($articles, $articles2);
    }
}
