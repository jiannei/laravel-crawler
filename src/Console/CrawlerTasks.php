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
use Jiannei\LaravelCrawler\Models\CrawlTask;
use Jiannei\LaravelCrawler\Support\Facades\Crawler;

class CrawlerTasks extends Command
{
    protected $signature = 'crawler:tasks';

    protected $description = 'Sync crawling tasks from data source.';

    public function handle()
    {
        $this->info("[{$this->description}]:starting ".now()->format('Y-m-d H:i:s'));

        Crawler::source()->each(function ($item) {
            $this->comment('syncing: '.$item['key']);

            $data = [
                'name' => $item['key'],
                'expression' => '* * * * *',
                'pattern' => $item,
            ];

            CrawlTask::query()->updateOrCreate(['name' => $data['name']], $data);
        });

        $this->info("[{$this->description}]:finished ".now()->format('Y-m-d H:i:s'));
    }
}
