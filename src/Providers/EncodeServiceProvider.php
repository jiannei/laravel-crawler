<?php

/*
 * This file is part of the jiannei/laravel-crawler.
 *
 * (c) jiannei <longjian.huang@foxmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Jiannei\LaravelCrawler\Providers;

use Jiannei\LaravelCrawler\Contracts\ServiceProviderContract;
use Jiannei\LaravelCrawler\Kernel;
use Jiannei\LaravelCrawler\Services\EncodeService;

class EncodeServiceProvider implements ServiceProviderContract
{
    public function register(Kernel $kernel)
    {
        $kernel->bind('encoding', function (string $outputEncoding, string $inputEncoding = null) {
            return EncodeService::convert($this, $outputEncoding, $inputEncoding);
        });
    }
}
