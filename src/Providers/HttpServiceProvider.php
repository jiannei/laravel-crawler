<?php

namespace Jiannei\LaravelCrawler\Providers;

use Jiannei\LaravelCrawler\Kernel;
use Jiannei\LaravelCrawler\Contracts\ServiceProviderContract;
use Jiannei\LaravelCrawler\Services\HttpService;
use Jiannei\LaravelCrawler\Services\MultiRequestService;

class HttpServiceProvider implements ServiceProviderContract
{
    public function register(Kernel $kernel)
    {
        $kernel->bind('get',function (...$args){
           return HttpService::get($this,...$args);
        });

        $kernel->bind('post',function (...$args){
            return HttpService::post($this,...$args);
        });

        $kernel->bind('postJson',function (...$args){
            return HttpService::postJson($this,...$args);
        });

        $kernel->bind('multiGet',function (...$args){
            return new MultiRequestService($this,'get',...$args);
        });

        $kernel->bind('multiPost',function (...$args){
            return new MultiRequestService($this,'post',...$args);
        });
    }
}