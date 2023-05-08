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
use Illuminate\Support\Str;
use Jiannei\LaravelCrawler\Contracts\ConsumeService;
use Jiannei\LaravelCrawler\Models\CrawlRecord;

class RecordConsume implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(private readonly CrawlRecord $record)
    {
    }

    public function handle(ConsumeService $service)
    {
        $method = Str::camel($this->record->task->pattern['key']);

        if (!method_exists($service, $method)) {
            throw new \InvalidArgumentException("$method not exist");
        }

        try {
            DB::transaction(function () use ($service, $method) {
                $service->$method($this->record);

                $this->record->consumed = true;
                $this->record->save();
            });
        } catch (\Throwable $e) {
            Log::channel('crawler')->debug('consume', ['exception' => $e->getMessage()]);
        }
    }
}
