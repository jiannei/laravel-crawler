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
use Illuminate\Contracts\Console\Isolatable;
use Jiannei\LaravelCrawler\Models\CrawlTask;
use Jiannei\LaravelCrawler\Support\Facades\Crawler;

class CrawlerTaskSync extends Command implements Isolatable
{
    protected $signature = 'crawler:task:sync {type=import}';

    protected $description = 'Sync crawling tasks.';

    public function handle()
    {
        $this->info("[{$this->description}]:starting ".now()->format('Y-m-d H:i:s'));

        $this->option('type') === 'export' ? $this->export() : $this->import();

        $this->info("[{$this->description}]:finished ".now()->format('Y-m-d H:i:s'));
    }

    protected function import()
    {
        Crawler::source()->each(function ($item) {
            $this->comment('importing: '.$item['key']);

            $data = [
                'name' => $item['key'],
                'expression' => $item['expression'] ?? '* * * * *',
                'pattern' => $item,
            ];

            CrawlTask::query()->updateOrCreate(['name' => $data['name']], $data);
        });
    }

    protected function export()
    {
        $tasks = CrawlTask::select('pattern')->where('active',true)->get();

        Crawler::source('json',$tasks->pluck('pattern')->all());
    }
}
