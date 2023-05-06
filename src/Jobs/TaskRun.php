<?php

namespace Jiannei\LaravelCrawler\Jobs;

use Cron\CronExpression;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Jiannei\LaravelCrawler\Models\CrawlTask;
use Jiannei\LaravelCrawler\Support\Facades\Crawler;

class TaskRun implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $afterCommit = true;

    public function __construct(private readonly CrawlTask $task)
    {

    }

    public function handle()
    {
        DB::transaction(function () {
            $result = [];

            try {
                $result = Crawler::pattern($this->task->pattern);
                $this->task->previous_run_date = Carbon::now();
                $this->task->next_run_date = Carbon::instance((new CronExpression($this->task->expression))->getNextRunDate());
                $this->task->status = 1;
                $this->task->exception = '';
            } catch (\Throwable $exception) {
                $this->task->status = -1;
                $this->task->exception = $exception;
            }

            $this->task->save();

            if (is_array($result)) {
                $result = collect($result);
            }

            $contents = $result->map(function ($item) {
                return ['content' => $item];
            });

            $this->task->records()->createMany($contents->all());
        });
    }
}