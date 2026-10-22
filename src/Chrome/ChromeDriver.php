<?php

/*
 * This file is part of the jiannei/laravel-crawler.
 *
 * (c) jiannei <jiannei@sinan.fun>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Jiannei\LaravelCrawler\Chrome;

use Facebook\WebDriver\WebDriverExpectedCondition;

interface ChromeDriver
{
    public function get(string $url): void;

    public function waitUntil(WebDriverExpectedCondition $condition, int $timeoutInSeconds, int $intervalInMilliseconds): void;

    public function html(): string;

    public function quit(): void;
}
