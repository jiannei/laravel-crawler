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
use Illuminate\Support\Str;
use Jiannei\LaravelCrawler\Contracts\ConsumeService;
use Jiannei\LaravelCrawler\Jobs\RecordConsume;
use Jiannei\LaravelCrawler\Models\CrawlRecord;

class CrawlerRecordConsume extends Command
{
    protected $signature = 'crawler:record:consume {name?} {--limit=1000}';

    protected $description = 'Consume crawled records.';

    public function handle(ConsumeService $service)
    {
        $this->info("[{$this->description}]:starting ".now()->format('Y-m-d H:i:s'));

        $records = CrawlRecord::select(['crawl_records.*','crawl_tasks.name'])
            ->join('crawl_tasks','crawl_tasks.id','=','crawl_records.task_id')
            ->where('crawl_tasks.active',true)
            ->where('crawl_records.consumed', false)
            ->when($this->argument('name'), function (Builder $query,string $name) {
                $query->where('crawl_tasks.name',$name);
            })
            ->orderBy('id')
            ->cursorPaginate($this->option('limit'));

        foreach ($records as $record) {
            $method = Str::camel($record->name);
            if (!method_exists($service, $method)) {
                $this->error("[{$this->description}]:error ".now()->format('Y-m-d H:i:s'));
                continue;
            }

            $this->comment("consuming:[$record->name] with {$method}");

            dispatch(new RecordConsume($record,$method));
        }

        $this->info("[{$this->description}]:finished ".now()->format('Y-m-d H:i:s'));
    }
}
