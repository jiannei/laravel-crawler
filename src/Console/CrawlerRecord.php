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
use Illuminate\Database\Eloquent\Builder;
use Jiannei\LaravelCrawler\Contracts\ConsumeService;
use Jiannei\LaravelCrawler\Jobs\RecordConsume;
use Jiannei\LaravelCrawler\Models\CrawlRecord;

class CrawlerRecord extends Command
{
    protected $signature = 'crawler:record {name?} {--limit=1000}';

    protected $description = 'Consume crawled records.';

    public function handle(ConsumeService $service)
    {
        $this->info("[{$this->description}]:starting ".now()->format('Y-m-d H:i:s'));

        $records = CrawlRecord::with('task')->select(['crawl_records.*', 'crawl_tasks.name', 'crawl_tasks.pattern'])
            ->join('crawl_tasks', 'crawl_tasks.id', '=', 'crawl_records.task_id')
            ->where('crawl_tasks.active', true)
            ->where('crawl_records.consumed', false)
            ->when($this->argument('name'), function (Builder $query, string $name) {
                $query->where('crawl_tasks.name', $name);
            })
            ->orderBy('id')
            ->cursorPaginate($this->option('limit'));

        foreach ($records as $record) {
            $method = $service->resolveCallbackMethod($record->task->pattern);

            if (!$service->valid($record->task->pattern)) {
                $this->error("error: {$method} not exist");
                continue;
            }

            $this->comment("consuming:[$record->name] - {$method}");

            dispatch(new RecordConsume($record->task->pattern, $record));
        }

        $this->info("[{$this->description}]:finished ".now()->format('Y-m-d H:i:s'));
    }
}
