<?php

namespace Jiannei\LaravelCrawler\Tests\Feature;


use Jiannei\LaravelCrawler\QueryList;
use Jiannei\LaravelCrawler\Tests\TestCase;

class InstanceTest extends TestCase
{
    protected $html;

    public function setUp(): void
    {
        $this->html = $this->getSnippet('snippet-1');
    }
    /**
     * @test
     */
    public function singleton_instance_mode()
    {
        $ql = QueryList::getInstance()->html($this->html);
        $ql2 = QueryList::getInstance();
        $this->assertEquals($ql->getHtml(),$ql2->getHtml());
    }

    /**
     * @test
     */
    public function get_new_object()
    {
        $ql = (new QueryList())->html($this->html);
        $ql2 = (new QueryList())->html('');
        $this->assertNotEquals($ql->getHtml(),$ql2->getHtml());

        $ql = QueryList::range('')->html($this->html);
        $ql2 = QueryList::range('')->html('');
        $this->assertNotEquals($ql->getHtml(),$ql2->getHtml());
    }
}