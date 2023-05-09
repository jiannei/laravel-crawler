<?php

/*
 * This file is part of the jiannei/laravel-crawler.
 *
 * (c) jiannei <longjian.huang@foxmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Jiannei\LaravelCrawler\Contracts;

interface ConsumeService
{
    public function process(array $pattern, array $content): bool;

    public function before();

    public function after();

    public function valid(array $pattern): bool;

    public function resolveCallbackMethod(array $pattern): string;
}
