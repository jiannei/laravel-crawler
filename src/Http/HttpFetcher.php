<?php

/*
 * This file is part of the jiannei/laravel-crawler.
 *
 * (c) jiannei <jiannei@sinan.fun>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Jiannei\LaravelCrawler\Http;

use Illuminate\Support\Facades\Http;
use Jiannei\LaravelCrawler\Contracts\Fetcher;
use Jiannei\LaravelCrawler\Data\FetchResponse;

class HttpFetcher implements Fetcher
{
    public function __construct(private readonly array $options = [])
    {
    }

    public function fetch(string $url, array|string|null $query = null, array $options = []): FetchResponse
    {
        $response = Http::withOptions(array_merge($this->options, $options))->get($url, $query);

        return new FetchResponse($response->status(), $response->body(), $response->headers());
    }
}
