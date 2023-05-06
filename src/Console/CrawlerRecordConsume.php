<?php

/*
 * This file is part of the jiannei/laravel-crawler.
 *
 * (c) jiannei <longjian.huang@foxmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Jiannei\LaravelCrawler\Console;

use Illuminate\Console\Command;
use Jiannei\LaravelCrawler\Jobs\RecordConsume;
use Jiannei\LaravelCrawler\Models\CrawlRecord;

class CrawlerRecordConsume extends Command
{
    protected $signature = 'crawler:record:consume {--limit=1000}';

    protected $description = 'Consume crawled records.';

    public function handle()
    {
        $this->info("[{$this->description}]:starting ".now()->format('Y-m-d H:i:s'));

        $records = CrawlRecord::with('task')->where('consumed', false)->cursorPaginate($this->option('limit'));

        foreach ($records as $record) {
            dispatch(new RecordConsume($record))->catch(function (\Throwable $e) {
                $this->error($e->getMessage());
            });
        }

        $this->info("[{$this->description}]:finished ".now()->format('Y-m-d H:i:s'));
    }
}
