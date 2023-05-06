<?php

/*
 * This file is part of the jiannei/laravel-crawler.
 *
 * (c) jiannei <longjian.huang@foxmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Jiannei\LaravelCrawler\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Jiannei\LaravelCrawler\Models\CrawlRecord;

class CrawlerRecordConsuming
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public CrawlRecord $record)
    {
    }
}
