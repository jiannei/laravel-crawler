<?php

/*
 * This file is part of the jiannei/laravel-crawler.
 *
 * (c) jiannei <jiannei@sinan.fun>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Jiannei\LaravelCrawler;

use Facebook\WebDriver\WebDriverExpectedCondition;
use Illuminate\Support\Collection;
use Jiannei\LaravelCrawler\Actions\PatternRun;
use Jiannei\LaravelCrawler\Contracts\Browser;
use Jiannei\LaravelCrawler\Contracts\Fetcher;
use Jiannei\LaravelCrawler\Data\RunOptions;
use Jiannei\LaravelCrawler\Exceptions\FetchFailed;
use Jiannei\LaravelCrawler\Exceptions\InvalidPattern;
use Jiannei\LaravelCrawler\Parsing\Page;
use Jiannei\LaravelCrawler\Sources\PatternSourceManager;

/**
 * The public entry point for crawls.
 */
class Crawler
{
    public function __construct(
        private readonly Fetcher $fetcher,
        private readonly PatternRun $run,
        private readonly PatternSourceManager $sources,
        private readonly Browser $browser,
    ) {
    }

    /**
     * Build a new page object.
     */
    public function new(\DOMNodeList|\DOMNode|array|string|null $node = null, ?string $uri = null, ?string $baseHref = null): Page
    {
        return new Page(...func_get_args());
    }

    /**
     * Fetch remote content into a page.
     */
    public function fetch(string $url, array|string|null $query = null, array $options = []): Page
    {
        $response = $this->fetcher->fetch($url, $query, $options);

        if (!$response->successful()) {
            throw new FetchFailed($url, $response->status, $response->body);
        }

        return new Page($response->body);
    }

    /**
     * Run a pattern and return its items.
     */
    public function pattern(array $pattern, RunOptions $options = new RunOptions()): Collection
    {
        return $this->run->run($pattern, $options);
    }

    /**
     * Run a stored pattern by its key.
     */
    public function json(string $key, RunOptions $options = new RunOptions()): Collection
    {
        $source = $this->sources->driver()->all();

        if (!$source->has($key)) {
            throw new InvalidPattern('url pattern not exist');
        }

        $pattern = $source->get($key);

        if (!empty($pattern['rss'])) {
            $options = new RunOptions(
                query: array_merge((array) ($pattern['query'] ?? []), $options->query),
                options: array_merge((array) ($pattern['options'] ?? []), $options->options),
                before: $options->before,
                after: $options->after,
            );

            return $this->rss($pattern['url'], [], $options);
        }

        return $this->pattern($pattern, $options);
    }

    /**
     * Parse an RSS feed.
     */
    public function rss(string $url, array $pattern = [], RunOptions $options = new RunOptions()): Collection
    {
        return $this->run->run([
            'url' => $url,
            'group' => $pattern ?: config('crawler.rss'),
            'rss' => true,
        ], $options);
    }

    /**
     * Get a JS rendering page.
     */
    public function chrome(string $url, WebDriverExpectedCondition $condition): Page
    {
        return $this->new($this->browser->html($url, $condition));
    }
}
