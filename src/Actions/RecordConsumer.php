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

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Jiannei\LaravelCrawler\Contracts\ConsumeService;
use Jiannei\LaravelCrawler\Data\ConsumeResult;
use Jiannei\LaravelCrawler\Models\CrawlRecord;
use Jiannei\LaravelCrawler\Models\CrawlTask;

/**
 * Coordinates one Consumption: the configured adapter, marking, and failure recording.
 */
final class RecordConsumer
{
    public function __construct(private readonly ?ConsumeService $service = null)
    {
    }

    public function configured(): bool
    {
        return null !== $this->service;
    }

    public function consume(CrawlTask $task, CrawlRecord $record): ConsumeResult
    {
        if (null === $this->service) {
            return $this->recordFailure($record, 'The consume service is not configured.');
        }

        if ($record->consumed) {
            return ConsumeResult::consumed();
        }

        $service = $this->service;

        try {
            return DB::transaction(function () use ($service, $task, $record) {
                $consumed = $service->consume($task, $record);

                $record->consumed = $consumed;
                $record->exception = null;
                $record->save();

                return $consumed ? ConsumeResult::consumed() : ConsumeResult::notConsumed();
            });
        } catch (\Throwable $e) {
            return $this->recordFailure($record, $e->getMessage(), $e);
        }
    }

    private function recordFailure(CrawlRecord $record, string $message, ?\Throwable $e = null): ConsumeResult
    {
        DB::transaction(function () use ($record, $message) {
            $record->consumed = false;
            $record->exception = $message;
            $record->save();
        });

        $context = [
            'record' => $record->getKey(),
            'message' => $message,
        ];

        if ($e) {
            $context['file'] = $e->getFile();
            $context['line'] = $e->getLine();
        }

        Log::channel('crawler')->debug('consume', $context);

        return ConsumeResult::failed($message);
    }
}
