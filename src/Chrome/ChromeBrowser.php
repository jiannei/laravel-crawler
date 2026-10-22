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
use Jiannei\LaravelCrawler\Contracts\Browser;

final class ChromeBrowser implements Browser
{
    public function __construct(
        private readonly ChromeSettings $settings,
        private readonly ChromeDriverFactory $drivers,
    ) {
    }

    public function html(string $url, WebDriverExpectedCondition $condition): string
    {
        $driver = $this->drivers->create($this->settings);

        try {
            $driver->get($url);
            $driver->waitUntil(
                $condition,
                $this->settings->waitTimeoutInSeconds,
                $this->settings->waitIntervalInMilliseconds,
            );

            return $driver->html();
        } finally {
            $driver->quit();
        }
    }
}
