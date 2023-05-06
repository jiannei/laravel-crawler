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

class CrawlerTaskRun extends Command implements Isolatable
{
    protected $signature = 'crawler:task:run';

    protected $description = 'Run crawling tasks';

    public function handle()
    {
        $this->info("[{$this->description}]:starting ".now()->format('Y-m-d H:i:s'));

        $tasks = CrawlTask::where('active', true)->where(function (Builder $query) {
            $query->where('next_run_date', '<=', Carbon::now())
                ->orWhereNull('next_run_date');
        })->get();

        $tasks->each(function ($task) {
            $this->comment('running:'.$task->name);

            dispatch(new TaskRun($task))->catch(function (\Throwable $e) {
                $this->error($e->getMessage());
            });
        });

        $this->info("[{$this->description}]:finished ".now()->format('Y-m-d H:i:s'));
    }
}
