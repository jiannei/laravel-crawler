<?php

/*
 * This file is part of the jiannei/laravel-response.
 *
 * (c) Jiannei <longjian.huang@foxmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Jiannei\LaravelCrawler\Tests;

abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        // additional setup
    }

    protected function getSnippet($name)
    {
        return file_get_contents(__DIR__.'/assets/'.$name.'.html');
    }
}
