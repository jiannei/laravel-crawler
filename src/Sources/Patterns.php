<?php

/*
 * This file is part of the jiannei/laravel-crawler.
 *
 * (c) jiannei <jiannei@sinan.fun>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Jiannei\LaravelCrawler\Sources;

use Illuminate\Support\Collection;
use Jiannei\LaravelCrawler\Exceptions\InvalidPattern;

/**
 * Shared key rules for stored patterns.
 */
final class Patterns
{
    public static function keyed(array $patterns): Collection
    {
        $keyed = collect();

        foreach ($patterns as $pattern) {
            if (!is_array($pattern)) {
                throw new InvalidPattern('A stored pattern must be an array.');
            }

            $key = $pattern['key'] ?? null;

            if (!is_string($key) || '' === $key) {
                throw new InvalidPattern('A stored pattern requires a [key].');
            }

            if ($keyed->has($key)) {
                throw new InvalidPattern("The pattern key [$key] is duplicated.");
            }

            $keyed->put($key, $pattern);
        }

        return $keyed;
    }
}
