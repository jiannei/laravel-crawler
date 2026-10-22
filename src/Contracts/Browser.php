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

use Facebook\WebDriver\WebDriverExpectedCondition;

interface Browser
{
    /**
     * Render a URL and return the final HTML after the wait condition.
     */
    public function html(string $url, WebDriverExpectedCondition $condition): string;
}
