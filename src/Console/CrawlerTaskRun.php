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

use Cron\CronExpression;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Jiannei\LaravelCrawler\Models\CrawlTask;
use Jiannei\LaravelCrawler\Support\Facades\Crawler;

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

            dispatch(function () use ($task) {
                DB::transaction(function () use ($task) {
                    $result = [];

                    try {
                        $result = Crawler::pattern($task->pattern);
                        $task->previous_run_date = Carbon::now();
                        $task->next_run_date = Carbon::instance((new CronExpression($task->expression))->getNextRunDate());
                        $task->status = 1;
                        $task->exception = '';
                    } catch (\Throwable $exception) {
                        $task->status = -1;
                        $task->exception = $exception;
                    }

                    $task->save();

                    if (is_array($result)) {
                        $result = collect($result);
                    }

                    $contents = $result->map(function ($item) {
                        return ['content' => $item];
                    });

                    $task->records()->createMany($contents->all());
                });
            });
        });

        $this->info("[{$this->description}]:finished ".now()->format('Y-m-d H:i:s'));
    }
}
