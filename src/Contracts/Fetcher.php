<?php

/*
 * This file is part of the jiannei/laravel-crawler.
 *
 * (c) jiannei <jiannei@sinan.fun>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Jiannei\LaravelCrawler\Contracts;

use Jiannei\LaravelCrawler\Data\FetchResponse;

interface Fetcher
{
    public function fetch(string $url, array|string|null $query = null, array $options = []): FetchResponse;
}
