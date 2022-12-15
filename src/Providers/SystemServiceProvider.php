<?php

namespace Jiannei\LaravelCrawler\Providers;

use Closure;
use Jiannei\LaravelCrawler\Kernel;
use Jiannei\LaravelCrawler\Contracts\ServiceProviderContract;

class SystemServiceProvider implements ServiceProviderContract
{
    public function register(Kernel $kernel)
    {
        $kernel->bind('html',function (...$args){
            $this->setHtml(...$args);
            return $this;
        });

        $kernel->bind('queryData',function (Closure $callback = null){
            return $this->query()->getData($callback)->all();
        });

        $kernel->bind('pipe',function (Closure $callback = null){
            return $callback($this);
        });

    }
}