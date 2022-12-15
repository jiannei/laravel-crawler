<?php

namespace Jiannei\LaravelCrawler\Tests\Feature;


use Jiannei\LaravelCrawler\QueryList;
use Jiannei\LaravelCrawler\Tests\TestCase;

class MethodTest extends TestCase
{
    protected $html;

    public function setUp(): void
    {
        $this->html = $this->getSnippet('snippet-1');
    }

    /**
     * @test
     */
    public function pipe()
    {
        $html = $this->html;
        $qlHtml = QueryList::pipe(function(QueryList $ql) use($html){
            $ql->setHtml($html);
            return $ql;
        })->getHtml(false);
        $this->assertEquals($html,$qlHtml);
    }
}