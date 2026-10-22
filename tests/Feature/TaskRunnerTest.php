<?php

/*
 * This file is part of the jiannei/laravel-crawler.
 *
 * (c) jiannei <jiannei@sinan.fun>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Jiannei\LaravelCrawler\Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Jiannei\LaravelCrawler\Actions\TaskRunner;
use Jiannei\LaravelCrawler\Contracts\Fetcher;
use Jiannei\LaravelCrawler\Data\FetchResponse;
use Jiannei\LaravelCrawler\Enums\TaskStatus;
use Jiannei\LaravelCrawler\Jobs\TaskRun;
use Jiannei\LaravelCrawler\Models\CrawlTask;
use Jiannei\LaravelCrawler\Tests\TestCase;

class TaskRunnerTest extends TestCase
{
    public function testItCrawlsOutsideTheTransactionAndStoresTheRecords(): void
    {
        $response = $this->response();

        $fetcher = new class($response) implements Fetcher {
            public int $transactionLevel = -1;

            public function __construct(private readonly FetchResponse $response)
            {
            }

            public function fetch(string $url, array|string|null $query = null, array $options = []): FetchResponse
            {
                $this->transactionLevel = DB::transactionLevel();

                return $this->response;
            }
        };

        $this->app->instance(Fetcher::class, $fetcher);

        $task = $this->task();

        $status = $this->app->make(TaskRunner::class)->run($task);

        $this->assertSame(TaskStatus::Succeeded, $status);
        $this->assertSame(0, $fetcher->transactionLevel);

        $task->refresh();

        $this->assertSame(TaskStatus::Succeeded, $task->status);
        $this->assertNull($task->exception);
        $this->assertNotNull($task->previous_run_date);
        $this->assertTrue($task->next_run_date->greaterThan($task->previous_run_date));
        $this->assertCount(1, $task->records);
        $this->assertSame('A single page', $task->records->first()->content['title']);
    }

    public function testAFailedCrawlIsRecordedAndWaitsForTheNextSchedule(): void
    {
        $this->fakeFetcher(new FetchResponse(503, 'down'));

        $task = $this->task();

        $status = $this->app->make(TaskRunner::class)->run($task);

        $this->assertSame(TaskStatus::Failed, $status);

        $task->refresh();

        $this->assertSame(TaskStatus::Failed, $task->status);
        $this->assertStringContainsString('503', $task->exception);
        $this->assertNotNull($task->previous_run_date);
        $this->assertNotNull($task->next_run_date);
        $this->assertTrue($task->next_run_date->greaterThan($task->previous_run_date));
        $this->assertCount(0, $task->records);
    }

    public function testAnInvalidCronExpressionFailsBeforeFetching(): void
    {
        $fetcher = $this->fakeFetcher($this->response());

        $task = $this->task(expression: 'not a cron');

        $status = $this->app->make(TaskRunner::class)->run($task);

        $this->assertSame(TaskStatus::Failed, $status);
        $this->assertCount(0, $fetcher->requests);

        $task->refresh();

        $this->assertSame(TaskStatus::Failed, $task->status);
        $this->assertNotNull($task->exception);
        $this->assertNotNull($task->previous_run_date);
        $this->assertNull($task->next_run_date);
    }

    public function testTheJobDelegatesToTheRunner(): void
    {
        $this->fakeFetcher($this->response());

        $task = $this->task();

        (new TaskRun($task))->handle($this->app->make(TaskRunner::class));

        $this->assertSame(TaskStatus::Succeeded, $task->refresh()->status);
    }

    public function testTheCommandDispatchesOnlyDueAndActiveTasks(): void
    {
        Queue::fake();

        $this->task(name: 'due');
        $this->task(name: 'future', nextRunDate: now()->addDay());
        $this->task(name: 'inactive', active: false);

        $this->artisan('crawler:task')
            ->assertExitCode(0);

        Queue::assertPushed(TaskRun::class, 1);
    }

    private function task(
        string $name = 'task:test',
        string $expression = '* * * * *',
        bool $active = true,
        ?Carbon $nextRunDate = null,
    ): CrawlTask {
        $task = CrawlTask::create([
            'name' => $name,
            'expression' => $expression,
            'active' => $active,
            'pattern' => [
                'key' => $name,
                'url' => 'https://example.test/single',
                'rules' => ['title' => ['#title', 'text']],
            ],
        ]);

        if (null !== $nextRunDate) {
            $task->next_run_date = $nextRunDate;
            $task->save();
        }

        return $task;
    }
}
