<?php

namespace Jiannei\LaravelCrawler\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Jiannei\LaravelCrawler\Models\CrawlRecord;

class CrawlerRecordConsuming
{
    use Dispatchable, SerializesModels;

    public function __construct(public CrawlRecord $record)
    {

    }
}