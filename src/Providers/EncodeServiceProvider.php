<?php

namespace Jiannei\LaravelCrawler\Providers;

use Jiannei\LaravelCrawler\Contracts\ServiceProviderContract;
use Jiannei\LaravelCrawler\Kernel;
use Jiannei\LaravelCrawler\Services\EncodeService;

class EncodeServiceProvider implements ServiceProviderContract
{
    public function register(Kernel $kernel)
    {
        $kernel->bind('encoding',function (string $outputEncoding,string $inputEncoding = null){
            return EncodeService::convert($this,$outputEncoding,$inputEncoding);
        });
    }
}