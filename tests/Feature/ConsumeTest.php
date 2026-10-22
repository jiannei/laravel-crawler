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

use Illuminate\Support\Facades\Queue;
use Jiannei\LaravelCrawler\Actions\RecordConsumer;
use Jiannei\LaravelCrawler\Contracts\ConsumeService;
use Jiannei\LaravelCrawler\Jobs\RecordConsume;
use Jiannei\LaravelCrawler\Models\CrawlTask;
use Jiannei\LaravelCrawler\Tests\Support\FakeConsumeService;
use Jiannei\LaravelCrawler\Tests\TestCase;

class ConsumeTest extends TestCase
{
    public function testConsumesARecordAndClearsAPreviousException(): void
    {
        [$task, $record] = $this->taskAndRecord();
        $record->exception = 'previous failure';
        $record->save();

        $service = new FakeConsumeService(fn () => true);
        $this->app->instance(ConsumeService::class, $service);

        $result = $this->app->make(RecordConsumer::class)->consume($task, $record);

        $this->assertTrue($result->consumed);
        $this->assertFalse($result->isFailed());
        $this->assertSame(1, $service->calls);

        $record->refresh();

        $this->assertTrue($record->consumed);
        $this->assertNull($record->exception);
    }

    public function testNotConsumedLeavesTheRecordPendingWithoutAnError(): void
    {
        [$task, $record] = $this->taskAndRecord();

        $this->app->instance(ConsumeService::class, new FakeConsumeService(fn () => false));

        $result = $this->app->make(RecordConsumer::class)->consume($task, $record);

        $this->assertFalse($result->consumed);
        $this->assertFalse($result->isFailed());

        $record->refresh();

        $this->assertFalse($record->consumed);
        $this->assertNull($record->exception);
    }

    public function testAFailureIsRecordedAndTheRecordStaysPending(): void
    {
        [$task, $record] = $this->taskAndRecord();

        $this->app->instance(ConsumeService::class, new FakeConsumeService(
            fn () => throw new \RuntimeException('consume boom')
        ));

        $result = $this->app->make(RecordConsumer::class)->consume($task, $record);

        $this->assertFalse($result->consumed);
        $this->assertTrue($result->isFailed());
        $this->assertSame('consume boom', $result->error);

        $record->refresh();

        $this->assertFalse($record->consumed);
        $this->assertSame('consume boom', $record->exception);
    }

    public function testAFailedConsumptionRollsBackTheCallbackWrites(): void
    {
        [$task, $record] = $this->taskAndRecord();

        $this->app->instance(ConsumeService::class, new FakeConsumeService(function (CrawlTask $task) {
            $task->exception = 'partial write';
            $task->save();

            throw new \RuntimeException('consume boom');
        }));

        $this->app->make(RecordConsumer::class)->consume($task, $record);

        $this->assertNull($task->refresh()->exception);
        $this->assertSame('consume boom', $record->refresh()->exception);
    }

    public function testAnAlreadyConsumedRecordIsNotConsumedAgain(): void
    {
        [$task, $record] = $this->taskAndRecord(consumed: true);

        $service = new FakeConsumeService(fn () => true);
        $this->app->instance(ConsumeService::class, $service);

        $result = $this->app->make(RecordConsumer::class)->consume($task, $record);

        $this->assertTrue($result->consumed);
        $this->assertSame(0, $service->calls);
    }

    public function testAnUnconfiguredConsumerRecordsAFailure(): void
    {
        [$task, $record] = $this->taskAndRecord();

        $consumer = $this->app->make(RecordConsumer::class);

        $this->assertFalse($consumer->configured());

        $result = $consumer->consume($task, $record);

        $this->assertTrue($result->isFailed());
        $this->assertSame('The consume service is not configured.', $result->error);
        $this->assertSame('The consume service is not configured.', $record->refresh()->exception);
    }

    public function testTheCommandFailsWhenTheServiceIsNotConfigured(): void
    {
        $this->artisan('crawler:record')
            ->assertExitCode(1);
    }

    public function testTheCommandDispatchesOneJobForEachPendingRecord(): void
    {
        Queue::fake();

        $this->app->instance(ConsumeService::class, new FakeConsumeService(fn () => true));

        $this->taskAndRecord();
        $this->taskAndRecord(name: 'consume:done', consumed: true);

        $this->artisan('crawler:record')
            ->assertExitCode(0);

        Queue::assertPushed(RecordConsume::class, 1);
    }

    public function testTheJobDelegatesToOneConsumption(): void
    {
        $this->app->instance(ConsumeService::class, new FakeConsumeService(fn () => true));

        [$task, $record] = $this->taskAndRecord();

        (new RecordConsume($task, $record))->handle($this->app->make(RecordConsumer::class));

        $this->assertTrue($record->refresh()->consumed);
    }

    private function taskAndRecord(string $name = 'consume:test', bool $consumed = false): array
    {
        $task = CrawlTask::create([
            'name' => $name,
            'expression' => '* * * * *',
            'pattern' => [
                'key' => $name,
                'url' => 'https://example.test/single',
                'rules' => ['title' => ['#title', 'text']],
                'consume' => 'defaultCallback',
            ],
        ]);

        $record = $task->records()->create([
            'content' => ['title' => 'A single page'],
        ]);

        if ($consumed) {
            $record->consumed = true;
            $record->save();
        }

        return [$task, $record];
    }
}
