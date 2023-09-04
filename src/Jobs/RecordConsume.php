<?php

/*
 * This file is part of the jiannei/laravel-crawler.
 *
 * (c) jiannei <longjian.huang@foxmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Jiannei\LaravelCrawler\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Jiannei\LaravelCrawler\Contracts\ConsumeService;
use Jiannei\LaravelCrawler\Models\CrawlRecord;
use Jiannei\LaravelCrawler\Models\CrawlTask;

class RecordConsume implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(protected readonly CrawlTask $task, protected readonly CrawlRecord $record)
    {
        $this->afterCommit();
    }

    public function handle(ConsumeService $service)
    {
        try {
            DB::transaction(function () use ($service) {
                $flag = $service->process($this->task, $this->record);

                $this->record->consumed = $flag;
                $this->record->save();
            });
        } catch (\Throwable $e) {
            Log::channel('crawler')->debug('consume', ['file' => $e->getFile(), 'line' => $e->getLine(), 'message' => $e->getMessage()]);
        }
    }
}
