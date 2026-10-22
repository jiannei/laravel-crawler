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

use Illuminate\Support\Collection;

interface PatternSource
{
    /**
     * All stored patterns, keyed by their `key`.
     *
     * @return Collection<string, array>
     */
    public function all(): Collection;

    /**
     * Save a set of patterns; patterns absent from the input are no longer current.
     *
     * @param array<int, array> $patterns
     */
    public function save(array $patterns): void;
}
