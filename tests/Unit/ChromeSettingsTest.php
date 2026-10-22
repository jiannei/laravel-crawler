<?php

/*
 * This file is part of the jiannei/laravel-crawler.
 *
 * (c) jiannei <jiannei@sinan.fun>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Jiannei\LaravelCrawler\Tests\Unit;

use Jiannei\LaravelCrawler\Chrome\ChromeSettings;
use Jiannei\LaravelCrawler\Tests\TestCase;

class ChromeSettingsTest extends TestCase
{
    public function testItBuildsChromeSettingsFromConfig(): void
    {
        $settings = ChromeSettings::fromConfig($this->settingsConfig());

        $this->assertSame('http://localhost', $settings->serverUrl);
        $this->assertSame(4444, $settings->port);
        $this->assertSame('http://localhost:4444', $settings->endpoint());
        $this->assertSame(['--headless'], $settings->arguments);
        $this->assertSame(30, $settings->waitTimeoutInSeconds);
        $this->assertSame(250, $settings->waitIntervalInMilliseconds);
        $this->assertSame(5000, $settings->connectionTimeoutInMilliseconds);
        $this->assertSame(10000, $settings->requestTimeoutInMilliseconds);
        $this->assertSame('chromedriver', $settings->binary);
        $this->assertSame('/tmp/crawler-server.log', $settings->logPath);
        $this->assertSame('INFO', $settings->logLevel);
    }

    public function testItKeepsTheServerPathInTheEndpoint(): void
    {
        $config = $this->settingsConfig();
        $config['server']['url'] = 'http://localhost/wd/hub';

        $this->assertSame('http://localhost:4444/wd/hub', ChromeSettings::fromConfig($config)->endpoint());
    }

    public function testItProvidesDefaults(): void
    {
        $settings = ChromeSettings::fromConfig([
            'server' => ['url' => 'http://localhost'],
        ]);

        $this->assertSame([], $settings->arguments);
        $this->assertSame(30, $settings->waitTimeoutInSeconds);
        $this->assertSame(250, $settings->waitIntervalInMilliseconds);
        $this->assertSame(30000, $settings->connectionTimeoutInMilliseconds);
        $this->assertSame(30000, $settings->requestTimeoutInMilliseconds);
        $this->assertSame('chromedriver', $settings->binary);
        $this->assertNotSame('', $settings->logPath);
        $this->assertSame('INFO', $settings->logLevel);
    }

    public function testItRejectsAPortInTheServerUrl(): void
    {
        $config = $this->settingsConfig();
        $config['server']['url'] = 'http://localhost:4444';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must not contain a port');

        ChromeSettings::fromConfig($config);
    }

    public function testItRejectsAnInvalidPort(): void
    {
        $config = $this->settingsConfig();
        $config['server']['port'] = 0;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('crawler.chrome.server.port');

        ChromeSettings::fromConfig($config);
    }

    public function testItRejectsNonStringArguments(): void
    {
        $config = $this->settingsConfig();
        $config['arguments'] = ['--headless', 123];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('crawler.chrome.arguments');

        ChromeSettings::fromConfig($config);
    }

    public function testItRejectsAnInvalidWaitInterval(): void
    {
        $config = $this->settingsConfig();
        $config['wait']['interval_in_millisecond'] = 0;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('crawler.chrome.wait.interval_in_millisecond');

        ChromeSettings::fromConfig($config);
    }

    private function settingsConfig(): array
    {
        return [
            'server' => [
                'url' => 'http://localhost',
                'port' => 4444,
                'connection_timeout_in_ms' => 5000,
                'request_timeout_in_ms' => 10000,
            ],
            'binary' => 'chromedriver',
            'arguments' => ['--headless'],
            'wait' => [
                'timeout_in_second' => 30,
                'interval_in_millisecond' => 250,
            ],
            'log' => [
                'path' => '/tmp/crawler-server.log',
                'level' => 'INFO',
            ],
        ];
    }
}
