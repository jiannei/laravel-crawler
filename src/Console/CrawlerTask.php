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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Jiannei\LaravelCrawler\Jobs\TaskRun;
use Jiannei\LaravelCrawler\Models\CrawlTask;
use Jiannei\LaravelCrawler\Support\Facades\Crawler;

class CrawlerTask extends Command implements Isolatable
{
    protected $signature = 'crawler:task {name?} {--action=run}';

    protected $description = 'Manage crawler tasks.';

    public function handle()
    {
        $this->info("[{$this->description}]:starting ".now()->format('Y-m-d H:i:s'));

        match ($this->option('action')) {
            default => $this->runTask(),
            'export' => $this->export(),
            'import' => $this->import()
        };

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
        $this->comment('exporting...');

        $tasks = CrawlTask::select('pattern')->where('active', true)->get();

        Crawler::source('json', $tasks->pluck('pattern')->all());
    }

    protected function runTask()
    {
        $tasks = CrawlTask::where('active', true)
            ->where(function (Builder $query) {
                $query->where('next_run_date', '<=', Carbon::now())
                    ->orWhereNull('next_run_date');
            })
            ->when($this->argument('name'), function (Builder $query, string $name) {
                $query->where('name', $name);
            })
            ->get();

        $tasks->each(function ($task) {
            $this->comment('running:'.$task->name);

            dispatch(new TaskRun($task));
        });
    }
}
