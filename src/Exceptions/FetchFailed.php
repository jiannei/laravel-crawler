<?php

/*
 * This file is part of the jiannei/laravel-crawler.
 *
 * (c) jiannei <jiannei@sinan.fun>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Jiannei\LaravelCrawler\Exceptions;

class FetchFailed extends \RuntimeException
{
    public function __construct(
        public readonly string $url,
        public readonly int $status,
        public readonly string $body,
    ) {
        parent::__construct("Fetch failed for [$url] with HTTP status [$status].");
    }
}
