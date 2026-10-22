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
use Illuminate\Support\Facades\File;
use Jiannei\LaravelCrawler\Contracts\PatternSource;
use Jiannei\LaravelCrawler\Exceptions\InvalidPattern;

class FilePatternSource implements PatternSource
{
    public function __construct(private readonly string $path)
    {
    }

    public function all(): Collection
    {
        if (!File::exists($this->path)) {
            throw new InvalidPattern("The source file [{$this->path}] does not exist.");
        }

        try {
            $decoded = json_decode(File::get($this->path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvalidPattern("The source file [{$this->path}] contains invalid JSON.", 0, $e);
        }

        if (!is_array($decoded)) {
            throw new InvalidPattern("The source file [{$this->path}] must contain a JSON array.");
        }

        return Patterns::keyed($decoded);
    }

    public function save(array $patterns): void
    {
        $keyed = Patterns::keyed($patterns);

        $json = json_encode(
            $keyed->values()->all(),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
        );

        if (false === $json) {
            throw new \RuntimeException('Unable to encode the patterns as JSON.');
        }

        if (false === File::put($this->path, $json)) {
            throw new \RuntimeException("Unable to write the source file [{$this->path}].");
        }
    }
}
