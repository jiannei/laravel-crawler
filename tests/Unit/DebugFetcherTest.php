<?php

/*
 * This file is part of the jiannei/laravel-crawler.
 *
 * (c) jiannei <jiannei@sinan.fun>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Jiannei\LaravelCrawler\Tests\Unit;

use Jiannei\LaravelCrawler\Contracts\Fetcher;
use Jiannei\LaravelCrawler\Data\FetchResponse;
use Jiannei\LaravelCrawler\Http\DebugFetcher;
use Jiannei\LaravelCrawler\Tests\TestCase;
use Psr\Log\AbstractLogger;

class DebugFetcherTest extends TestCase
{
    public function testItLogsAStructuredSummaryOfASuccessfulFetch(): void
    {
        $inner = new class() implements Fetcher {
            public array $calls = [];

            public function fetch(string $url, array|string|null $query = null, array $options = []): FetchResponse
            {
                $this->calls[] = [$url, $query, $options];

                return new FetchResponse(200, 'body', [
                    'Content-Type' => ['text/html'],
                    'Set-Cookie' => ['session=secret'],
                ]);
            }
        };

        $logger = $this->logger();

        $response = (new DebugFetcher($inner, $logger))->fetch(
            'https://example.test',
            ['page' => 2, 'token' => 'secret'],
            [
                'headers' => [
                    'Authorization' => 'Bearer secret',
                    'Accept' => 'text/html',
                ],
                'on_stats' => static fn () => null,
            ],
        );

        $this->assertSame(200, $response->status);
        $this->assertSame('https://example.test', $inner->calls[0][0]);
        $this->assertSame(['page' => 2, 'token' => 'secret'], $inner->calls[0][1]);

        $this->assertCount(1, $logger->records);

        $record = $logger->records[0];

        $this->assertSame('debug', $record['level']);
        $this->assertSame('http', $record['message']);
        $this->assertSame('fetch.completed', $record['context']['event']);
        $this->assertSame('https://example.test', $record['context']['url']);
        $this->assertSame(['page' => 2, 'token' => '[redacted]'], $record['context']['query']);
        $this->assertSame([
            'headers' => [
                'Authorization' => '[redacted]',
                'Accept' => 'text/html',
            ],
            'on_stats' => '[Closure]',
        ], $record['context']['options']);
        $this->assertSame(200, $record['context']['status']);
        $this->assertIsFloat($record['context']['duration_ms']);
        $this->assertSame([
            'Content-Type' => ['text/html'],
            'Set-Cookie' => '[redacted]',
        ], $record['context']['response_headers']);
        $this->assertSame(4, $record['context']['body_length']);
    }

    public function testItLogsAndRethrowsAFailedFetch(): void
    {
        $exception = new \RuntimeException('boom');

        $inner = new class($exception) implements Fetcher {
            public function __construct(private readonly \RuntimeException $exception)
            {
            }

            public function fetch(string $url, array|string|null $query = null, array $options = []): FetchResponse
            {
                throw $this->exception;
            }
        };

        $logger = $this->logger();

        try {
            (new DebugFetcher($inner, $logger))->fetch('https://example.test', ['token' => 'secret']);
            $this->fail('Expected the inner fetcher to throw.');
        } catch (\RuntimeException $e) {
            $this->assertSame($exception, $e);
        }

        $this->assertCount(1, $logger->records);

        $record = $logger->records[0];

        $this->assertSame('error', $record['level']);
        $this->assertSame('http', $record['message']);
        $this->assertSame('fetch.failed', $record['context']['event']);
        $this->assertSame('https://example.test', $record['context']['url']);
        $this->assertSame(['token' => '[redacted]'], $record['context']['query']);
        $this->assertSame([], $record['context']['options']);
        $this->assertIsFloat($record['context']['duration_ms']);
        $this->assertSame(\RuntimeException::class, $record['context']['exception']);
        $this->assertSame('boom', $record['context']['message']);
        $this->assertArrayNotHasKey('status', $record['context']);
    }

    private function logger(): AbstractLogger
    {
        return new class() extends AbstractLogger {
            public array $records = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = [
                    'level' => $level,
                    'message' => $message,
                    'context' => $context,
                ];
            }
        };
    }
}
