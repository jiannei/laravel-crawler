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

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;

final class RemoteChromeDriver implements ChromeDriver
{
    public function __construct(private readonly RemoteWebDriver $driver)
    {
    }

    public function get(string $url): void
    {
        $this->driver->get($url);
    }

    public function waitUntil(WebDriverExpectedCondition $condition, int $timeoutInSeconds, int $intervalInMilliseconds): void
    {
        $this->driver->wait($timeoutInSeconds, $intervalInMilliseconds)->until($condition);
    }

    public function html(): string
    {
        return (string) $this->driver
            ->findElement(WebDriverBy::cssSelector('html'))
            ->getDomProperty('innerHTML');
    }

    public function quit(): void
    {
        $this->driver->quit();
    }
}
