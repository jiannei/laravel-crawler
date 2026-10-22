<?php

/*
 * This file is part of the jiannei/laravel-crawler.
 *
 * (c) jiannei <jiannei@sinan.fun>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Jiannei\LaravelCrawler\Concerns;

use Jiannei\LaravelCrawler\Exceptions\InvalidPattern;
use Jiannei\LaravelCrawler\Models\CrawlRecord;
use Jiannei\LaravelCrawler\Models\CrawlTask;

trait Consumable
{
    public function consume(CrawlTask $task, CrawlRecord $record): bool
    {
        $callback = $this->resolveConsumeCallback($task);

        $this->before();

        try {
            return (bool) $callback($record, $task);
        } finally {
            $this->after();
        }
    }

    /**
     * Resolve the callback named by the Pattern's consume key.
     */
    protected function resolveConsumeCallback(CrawlTask $task): callable
    {
        $method = $task->pattern['consume'] ?? null;

        if (!is_string($method) || '' === $method) {
            $method = 'defaultCallback';
        }

        if (!method_exists($this, $method)) {
            throw new InvalidPattern("The consume callback [$method] does not exist.");
        }

        $callback = $this->{$method}();

        if (!is_callable($callback)) {
            throw new InvalidPattern("The consume callback [$method] must return a callable.");
        }

        return $callback;
    }

    protected function before()
    {
    }

    protected function after()
    {
    }
}
