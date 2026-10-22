<?php

/*
 * This file is part of the jiannei/laravel-crawler.
 *
 * (c) jiannei <jiannei@sinan.fun>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Jiannei\LaravelCrawler\Tests\Support;

use Jiannei\LaravelCrawler\Contracts\Fetcher;
use Jiannei\LaravelCrawler\Data\FetchResponse;

final class FakeFetcher implements Fetcher
{
    /** @var array<int, array{url: string, query: array|string|null, options: array}> */
    public array $requests = [];

    /** @var array<int, FetchResponse> */
    private array $responses;

    public function __construct(FetchResponse|array $responses)
    {
        $this->responses = $responses instanceof FetchResponse ? [$responses] : $responses;
    }

    public function fetch(string $url, array|string|null $query = null, array $options = []): FetchResponse
    {
        $this->requests[] = [
            'url' => $url,
            'query' => $query,
            'options' => $options,
        ];

        if (!$this->responses) {
            throw new \RuntimeException('No fake response queued.');
        }

        return array_shift($this->responses);
    }
}
