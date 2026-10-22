<?php

/*
 * This file is part of the jiannei/laravel-crawler.
 *
 * (c) jiannei <jiannei@sinan.fun>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Jiannei\LaravelCrawler\Contracts;

use Jiannei\LaravelCrawler\Models\CrawlRecord;
use Jiannei\LaravelCrawler\Models\CrawlTask;

interface ConsumeService
{
    /**
     * Consume one Record. Return false when the Record was not consumed but the attempt did not fail.
     */
    public function consume(CrawlTask $task, CrawlRecord $record): bool;
}
