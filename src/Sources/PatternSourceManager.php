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

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Arr;
use Jiannei\LaravelCrawler\Contracts\PatternSource;
use Jiannei\LaravelCrawler\Exceptions\InvalidPattern;

class PatternSourceManager
{
    public function __construct(
        private readonly Container $container,
        private readonly array $channels = [],
    ) {
    }

    public function driver(?string $name = null): PatternSource
    {
        $name ??= $this->container['config']->get('crawler.source.default', 'file');

        $channel = $this->channels[$name] ?? null;

        if (!is_array($channel)) {
            throw new InvalidPattern("The source channel [$name] is not configured.");
        }

        $driver = $channel['driver'] ?? null;

        if (!is_string($driver) || !class_exists($driver)) {
            throw new InvalidPattern("The source channel [$name] has no valid driver.");
        }

        $source = $this->container->make($driver, Arr::except($channel, ['driver']));

        if (!$source instanceof PatternSource) {
            throw new InvalidPattern("The source driver [$driver] must implement PatternSource.");
        }

        return $source;
    }
}
