<?php

/*
 * This file is part of the jiannei/laravel-crawler.
 *
 * (c) jiannei <jiannei@sinan.fun>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Jiannei\LaravelCrawler\Actions;

use Cron\CronExpression;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Jiannei\LaravelCrawler\Enums\TaskStatus;
use Jiannei\LaravelCrawler\Models\CrawlTask;

/**
 * Runs one Task: crawl outside the transaction, then commit its bookkeeping and Records together.
 */
final class TaskRunner
{
    public function __construct(private readonly PatternRun $run)
    {
    }

    public function run(CrawlTask $task): TaskStatus
    {
        try {
            $schedule = new CronExpression($task->expression);
        } catch (\Throwable $e) {
            $this->recordFailure($task, $e, Carbon::now(), null);

            return TaskStatus::Failed;
        }

        try {
            $items = $this->run->run($task->pattern);
        } catch (\Throwable $e) {
            $failedAt = Carbon::now();

            $this->recordFailure($task, $e, $failedAt, $this->nextRunDate($schedule, $failedAt));

            return TaskStatus::Failed;
        }

        $finishedAt = Carbon::now();

        $this->recordSuccess($task, $items, $finishedAt, $this->nextRunDate($schedule, $finishedAt));

        return TaskStatus::Succeeded;
    }

    private function recordSuccess(CrawlTask $task, Collection $items, Carbon $finishedAt, Carbon $nextRunDate): void
    {
        DB::transaction(function () use ($task, $items, $finishedAt, $nextRunDate) {
            $task->status = TaskStatus::Succeeded;
            $task->exception = null;
            $task->previous_run_date = $finishedAt;
            $task->next_run_date = $nextRunDate;
            $task->save();

            $task->records()->createMany(
                $items->map(function ($item) {
                    return ['content' => $item];
                })->all()
            );
        });
    }

    private function recordFailure(CrawlTask $task, \Throwable $e, Carbon $failedAt, ?Carbon $nextRunDate): void
    {
        DB::transaction(function () use ($task, $e, $failedAt, $nextRunDate) {
            $task->status = TaskStatus::Failed;
            $task->exception = $e->getMessage();
            $task->previous_run_date = $failedAt;
            $task->next_run_date = $nextRunDate;
            $task->save();
        });

        Log::channel('crawler')->debug('task', [
            'task' => $task->getKey(),
            'name' => $task->name,
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'message' => $e->getMessage(),
        ]);
    }

    private function nextRunDate(CronExpression $schedule, Carbon $from): Carbon
    {
        return Carbon::instance($schedule->getNextRunDate($from));
    }
}
