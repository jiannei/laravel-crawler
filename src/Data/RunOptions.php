<?php

/*
 * This file is part of the jiannei/laravel-crawler.
 *
 * (c) jiannei <jiannei@sinan.fun>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Jiannei\LaravelCrawler\Data;

final readonly class RunOptions
{
    public function __construct(
        public array $query = [],
        public array $options = [],
        public ?\Closure $before = null,
        public ?\Closure $after = null,
    ) {
    }
}
