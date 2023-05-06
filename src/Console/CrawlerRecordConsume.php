<?php

namespace Jiannei\LaravelCrawler\Console;

use Illuminate\Console\Command;
use Jiannei\LaravelCrawler\Events\CrawlerRecordConsuming;
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
            event(new CrawlerRecordConsuming($record));
        }

        $this->info("[{$this->description}]:finished ".now()->format('Y-m-d H:i:s'));
    }
}