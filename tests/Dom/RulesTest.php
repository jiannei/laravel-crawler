<?php

namespace Jiannei\LaravelCrawler\Tests\Dom;


use Illuminate\Support\Collection;
use Jiannei\LaravelCrawler\QueryList;
use Jiannei\LaravelCrawler\Tests\TestCase;

class RulesTest extends TestCase
{
    protected $html;
    protected $ql;

    public function setUp(): void
    {
        $this->html = $this->getSnippet('snippet-2');
        $this->ql = QueryList::html($this->html);
    }

    /**
     * @test
     */
    public function get_data_by_rules()
    {
        $rules = [
            'a' => ['a','text'],
            'img_src' => ['img','src'],
            'img_alt' => ['img','alt']
        ];
        $range = 'ul>li';
        $data = QueryList::rules($rules)->range($range)->html($this->html)->query()->getData();
        $this->assertInstanceOf(Collection::class,$data);
        $this->assertCount(3,$data);
        $this->assertEquals('http://querylist.com/2.jpg',$data[1]['img_src']);
    }
}