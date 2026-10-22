<?php

/*
 * This file is part of the jiannei/laravel-crawler.
 *
 * (c) jiannei <jiannei@sinan.fun>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Jiannei\LaravelCrawler\Tests\Support;

use Jiannei\LaravelCrawler\Contracts\ConsumeService;
use Jiannei\LaravelCrawler\Models\CrawlRecord;
use Jiannei\LaravelCrawler\Models\CrawlTask;

final class FakeConsumeService implements ConsumeService
{
    public int $calls = 0;

    public function __construct(public \Closure $callback)
    {
    }

    public function consume(CrawlTask $task, CrawlRecord $record): bool
    {
        ++$this->calls;

        return (bool) ($this->callback)($task, $record);
    }
}
