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

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;

final class RemoteChromeDriverFactory implements ChromeDriverFactory
{
    public function create(ChromeSettings $settings): ChromeDriver
    {
        $capabilities = DesiredCapabilities::chrome();

        $chromeOptions = new ChromeOptions();
        $chromeOptions->addArguments($settings->arguments);
        $capabilities->setCapability(ChromeOptions::CAPABILITY, $chromeOptions);

        $driver = RemoteWebDriver::create(
            $settings->endpoint(),
            $capabilities,
            $settings->connectionTimeoutInMilliseconds,
            $settings->requestTimeoutInMilliseconds,
        );

        return new RemoteChromeDriver($driver);
    }
}
