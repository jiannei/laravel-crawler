<?php

namespace Jiannei\LaravelCrawler\Tests\Feature;


use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Jiannei\LaravelCrawler\QueryList;
use Jiannei\LaravelCrawler\Tests\TestCase;

class HttpTest extends TestCase
{
    protected $urls = [
        'http://httpbin.org/get?name=php',
        'http://httpbin.org/get?name=golang',
        'http://httpbin.org/get?name=c++',
        'http://httpbin.org/get?name=java'
    ];

    public function testCanPostJsonData()
    {
        $mock = new MockHandler([new Response()]);
        $data = [
            'name' => 'foo'
        ];
        QueryList::postJson('http://foo.com',$data,[
            'handler' => $mock
        ]);
        $this->assertEquals((string)$mock->getLastRequest()->getBody(),json_encode($data));
    }

    public function testConcurrentRequestsBaseUse()
    {
        $urls = $this->urls;
        QueryList::getInstance()
            ->multiGet($urls)
            ->success(function(QueryList $ql,Response $response, $index) use($urls){
                $body = json_decode((string)$response->getBody(),true);
                $this->assertEquals($urls[$index],$body['url']);
            })->send();
    }

    public function testConcurrentRequestsAdvancedUse()
    {
        $ua = 'QueryList/4.0';

        $errorUrl = 'http://web-site-not-exist.com';
        $urls = array_merge($this->urls,[$errorUrl]);

        QueryList::rules([])
            ->multiGet($urls)
            ->concurrency(2)
            ->withOptions([
                'timeout' => 60
            ])
            ->withHeaders([
                'User-Agent' => $ua
            ])
            ->success(function (QueryList $ql, Response $response, $index) use($ua){
                $body = json_decode((string)$response->getBody(),true);
                $this->assertEquals($ua,$body['headers']['User-Agent']);
            })
            ->error(function (QueryList $ql, $reason, $index) use($urls,$errorUrl){
                $this->assertEquals($urls[$index],$errorUrl);
            })
            ->send();
    }


    public function testRequestWithCache()
    {
        $url = $this->urls[0];
        $data = QueryList::get($url,null,[
            'cache_ttl' => 600
        ])->getHtml();
        $data = json_decode($data,true);
        $this->assertEquals($url,$data['url']);
    }
}