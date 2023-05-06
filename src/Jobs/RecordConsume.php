<?php

namespace Jiannei\LaravelCrawler\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Jiannei\LaravelCrawler\Contracts\ConsumeService;
use Jiannei\LaravelCrawler\Models\CrawlRecord;

class RecordConsume implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly CrawlRecord $record)
    {

    }

    public function handle(ConsumeService $service)
    {
        $method = Str::camel($this->record->task->pattern['key']);

        if (!method_exists($service, $method)) {
            throw new \InvalidArgumentException("$method not exist");
        }

        $service->$method($this->record);
    }
}