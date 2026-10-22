<?php

/*
 * This file is part of the jiannei/laravel-crawler.
 *
 * (c) jiannei <jiannei@sinan.fun>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

// $suffix = now()->format('Y-m-d');

return [
    'fetch' => [
        'debug' => false, // log one structured summary per crawler fetch
    ],

    'source' => [
        'default' => env('CRAWLER_SOURCE_CHANNEL', 'file'),

        'channels' => [
            'file' => [
                'driver' => Jiannei\LaravelCrawler\Sources\FilePatternSource::class,
                'path' => resource_path('crawler.json'),
            ],
            'database' => [
                'driver' => Jiannei\LaravelCrawler\Sources\DatabasePatternSource::class,
                'model' => Jiannei\LaravelCrawler\Models\CrawlTask::class,
            ],
        ],
    ],

    'consume' => [
        // A class implementing Jiannei\LaravelCrawler\Contracts\ConsumeService.
        // Leave empty when this application only crawls.
        'service' => '',
    ],

    'log' => [
        'driver' => 'daily',
        'path' => storage_path('logs/crawler.log'),
        'level' => env('CRAWLER_LOG_LEVEL', 'debug'),
        'days' => 14,
    ],

    'guzzle' => [
        // https://docs.guzzlephp.org/en/stable/request-options.html
        'options' => [
            'debug' => false, // fopen(storage_path("logs/crawler-guzzle-{$suffix}.log"), 'a+')
            'connect_timeout' => 10,
            'http_errors' => false,
            'timeout' => 30,

            'headers' => [
                'Accept-Encoding' => 'gzip',
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/107.0.0.0 Safari/537.36',
            ],
        ],
    ],

    'chrome' => [
        'server' => [
            'url' => 'http://localhost', // scheme and host only; set the port below
            'port' => 4444,
            'connection_timeout_in_ms' => 30000,
            'request_timeout_in_ms' => 30000,
        ],
        'binary' => 'chromedriver', // name or path; crawler:server --path adds a directory prefix
        'arguments' => ['--headless', '--disable-gpu'], // https://chromedriver.chromium.org/capabilities
        'wait' => [
            'timeout_in_second' => 30,
            'interval_in_millisecond' => 250,
        ],
        'log' => [
            'path' => storage_path('logs/crawler-server.log'),
            'level' => 'INFO', // set log level: ALL, DEBUG, INFO, WARNING, SEVERE, OFF
        ],
    ],

    'rss' => [
        [
            'alias' => 'channel',
            'selector' => 'channel',
            'rules' => [
                'title' => ['title', 'text'],
                'link' => ['link', 'text'],
                'description' => ['description', 'text'],
                'pubDate' => ['pubDate', 'text'],
            ],
        ],
        [
            'alias' => 'items',
            'selector' => 'channel item',
            'rules' => [
                'category' => ['category', 'text'],
                'title' => ['title', 'text'],
                'description' => ['description', 'text'],
                'link' => ['link', 'text'],
                'guid' => ['guid', 'text'],
                'pubDate' => ['pubDate', 'text'],
            ],
        ],
    ],
];
