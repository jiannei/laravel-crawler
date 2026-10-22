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

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Jiannei\LaravelCrawler\Chrome\ChromeBrowser;
use Jiannei\LaravelCrawler\Chrome\ChromeDriver;
use Jiannei\LaravelCrawler\Chrome\ChromeDriverFactory;
use Jiannei\LaravelCrawler\Chrome\ChromeSettings;
use Jiannei\LaravelCrawler\Tests\TestCase;

class ChromeBrowserTest extends TestCase
{
    public function testItLoadsWaitsAndQuitsTheDriver(): void
    {
        $driver = new class implements ChromeDriver {
            public array $calls = [];

            public function get(string $url): void
            {
                $this->calls[] = ['get', $url];
            }

            public function waitUntil(WebDriverExpectedCondition $condition, int $timeoutInSeconds, int $intervalInMilliseconds): void
            {
                $this->calls[] = ['wait', $timeoutInSeconds, $intervalInMilliseconds];
            }

            public function html(): string
            {
                $this->calls[] = ['html'];

                return '<html><body>Rendered</body></html>';
            }

            public function quit(): void
            {
                $this->calls[] = ['quit'];
            }
        };

        $factory = new class($driver) implements ChromeDriverFactory {
            public int $created = 0;

            public ?ChromeSettings $settings = null;

            public function __construct(private readonly ChromeDriver $driver)
            {
            }

            public function create(ChromeSettings $settings): ChromeDriver
            {
                ++$this->created;
                $this->settings = $settings;

                return $this->driver;
            }
        };

        $settings = $this->settings();
        $browser = new ChromeBrowser($settings, $factory);

        $html = $browser->html('https://example.test', $this->condition());

        $this->assertSame('<html><body>Rendered</body></html>', $html);
        $this->assertSame(1, $factory->created);
        $this->assertSame($settings, $factory->settings);
        $this->assertSame([
            ['get', 'https://example.test'],
            ['wait', 30, 250],
            ['html'],
            ['quit'],
        ], $driver->calls);
    }

    public function testItQuitsTheDriverWhenWaitingFails(): void
    {
        $driver = new class implements ChromeDriver {
            public array $calls = [];

            public function get(string $url): void
            {
                $this->calls[] = 'get';
            }

            public function waitUntil(WebDriverExpectedCondition $condition, int $timeoutInSeconds, int $intervalInMilliseconds): void
            {
                $this->calls[] = 'wait';

                throw new \RuntimeException('wait failed');
            }

            public function html(): string
            {
                $this->calls[] = 'html';

                return '';
            }

            public function quit(): void
            {
                $this->calls[] = 'quit';
            }
        };

        $factory = new class($driver) implements ChromeDriverFactory {
            public function __construct(private readonly ChromeDriver $driver)
            {
            }

            public function create(ChromeSettings $settings): ChromeDriver
            {
                return $this->driver;
            }
        };

        $browser = new ChromeBrowser($this->settings(), $factory);

        try {
            $browser->html('https://example.test', $this->condition());
            $this->fail('Expected the wait to throw.');
        } catch (\RuntimeException $e) {
            $this->assertSame('wait failed', $e->getMessage());
        }

        $this->assertSame(['get', 'wait', 'quit'], $driver->calls);
    }

    private function settings(): ChromeSettings
    {
        return new ChromeSettings(
            serverUrl: 'http://localhost',
            port: 4444,
            arguments: ['--headless'],
            waitTimeoutInSeconds: 30,
            waitIntervalInMilliseconds: 250,
            connectionTimeoutInMilliseconds: 5000,
            requestTimeoutInMilliseconds: 10000,
            binary: 'chromedriver',
            logPath: '/tmp/crawler-server.log',
            logLevel: 'INFO',
        );
    }

    private function condition(): WebDriverExpectedCondition
    {
        return WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('body'));
    }
}
