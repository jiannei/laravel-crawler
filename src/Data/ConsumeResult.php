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

/**
 * The outcome of consuming one Record.
 */
final readonly class ConsumeResult
{
    private function __construct(
        public bool $consumed,
        public ?string $error = null,
    ) {
    }

    public static function consumed(): self
    {
        return new self(true);
    }

    public static function notConsumed(): self
    {
        return new self(false);
    }

    public static function failed(string $error): self
    {
        return new self(false, $error);
    }

    public function isFailed(): bool
    {
        return null !== $this->error;
    }
}
