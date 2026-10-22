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

use Jiannei\LaravelCrawler\Concerns\Consumable;
use Jiannei\LaravelCrawler\Contracts\ConsumeService;
use Jiannei\LaravelCrawler\Exceptions\InvalidPattern;
use Jiannei\LaravelCrawler\Models\CrawlRecord;
use Jiannei\LaravelCrawler\Models\CrawlTask;
use Jiannei\LaravelCrawler\Tests\TestCase;

class ConsumableTest extends TestCase
{
    public function testItRunsTheNamedCallbackBetweenHooks(): void
    {
        $consumer = new class implements ConsumeService {
            use Consumable;

            public array $events = [];

            protected function before()
            {
                $this->events[] = 'before';
            }

            protected function after()
            {
                $this->events[] = 'after';
            }

            protected function defaultCallback(): callable
            {
                return fn () => false;
            }

            protected function named(): callable
            {
                return function () {
                    $this->events[] = 'callback';

                    return true;
                };
            }
        };

        $result = $consumer->consume($this->task('named'), new CrawlRecord());

        $this->assertTrue($result);
        $this->assertSame(['before', 'callback', 'after'], $consumer->events);
    }

    public function testAnEmptyConsumeKeyFallsBackToTheDefaultCallback(): void
    {
        $consumer = new class implements ConsumeService {
            use Consumable;

            public array $events = [];

            protected function after()
            {
                $this->events[] = 'after';
            }

            protected function defaultCallback(): callable
            {
                return function () {
                    $this->events[] = 'default';

                    return true;
                };
            }
        };

        $result = $consumer->consume($this->task(''), new CrawlRecord());

        $this->assertTrue($result);
        $this->assertSame(['default', 'after'], $consumer->events);
    }

    public function testAnUnknownCallbackIsRejected(): void
    {
        $consumer = new class implements ConsumeService {
            use Consumable;

            protected function defaultCallback(): callable
            {
                return fn () => true;
            }
        };

        $this->expectException(InvalidPattern::class);

        $consumer->consume($this->task('missing'), new CrawlRecord());
    }

    public function testAfterRunsWhenTheCallbackThrows(): void
    {
        $consumer = new class implements ConsumeService {
            use Consumable;

            public array $events = [];

            protected function after()
            {
                $this->events[] = 'after';
            }

            protected function defaultCallback(): callable
            {
                return fn () => throw new \RuntimeException('boom');
            }
        };

        try {
            $consumer->consume($this->task(''), new CrawlRecord());
            $this->fail('Expected the callback to throw.');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $this->assertSame(['after'], $consumer->events);
    }

    private function task(string $consume): CrawlTask
    {
        return new CrawlTask([
            'pattern' => ['consume' => $consume],
        ]);
    }
}
