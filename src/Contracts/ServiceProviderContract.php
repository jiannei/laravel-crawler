<?php

namespace Jiannei\LaravelCrawler\Contracts;


use Jiannei\LaravelCrawler\Kernel;

interface ServiceProviderContract
{
    public function register(Kernel $kernel);
}