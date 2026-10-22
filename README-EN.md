<h1 align="center"> laravel-crawler </h1>

[简体中文](README.md) | [ENGLISH](README-EN.md)

> A pattern-driven crawler for Laravel.

> **📝 Version note:** This documentation targets the v3 development line. v3 is organized with Laravel-style directories, and its public namespaces are not compatible with v2. Class names below follow v3.

[![Test](https://github.com/jiannei/laravel-crawler/actions/workflows/test.yml/badge.svg)](https://github.com/jiannei/laravel-crawler/actions/workflows/test.yml)
[![StyleCI](https://github.styleci.io/repos/585477175/shield?branch=main&style=flat)](https://github.styleci.io/repos/585477175/shield?branch=main&style=flat)
[![Pint](https://img.shields.io/badge/Code%20Style-Pint-orange?style=flat)](https://laravel.com/docs/pint)
[![Latest Stable Version](http://poser.pugx.org/jiannei/laravel-crawler/v)](https://packagist.org/packages/jiannei/laravel-crawler)
[![Total Downloads](http://poser.pugx.org/jiannei/laravel-crawler/downloads)](https://packagist.org/packages/jiannei/laravel-crawler)
[![Monthly Downloads](http://poser.pugx.org/jiannei/laravel-crawler/d/monthly)](https://packagist.org/packages/jiannei/laravel-crawler)
[![Latest Unstable Version](http://poser.pugx.org/jiannei/laravel-crawler/v/unstable)](https://packagist.org/packages/jiannei/laravel-crawler)
[![License](http://poser.pugx.org/jiannei/laravel-crawler/license)](https://packagist.org/packages/jiannei/laravel-crawler)
[![PHP Version Require](http://poser.pugx.org/jiannei/laravel-crawler/require/php)](https://packagist.org/packages/jiannei/laravel-crawler)

## Introduce

`laravel-crawler` puts the full "request → parse → store → consume" pipeline into one package.

A Pattern describes what to crawl and how to parse it:

- Pass a Pattern directly to `Crawler::pattern()`, or store it in the file source / database source and run it by key with `Crawler::json()`
- `Crawler::fetch()` and `Crawler::new()` return a `Page`, which keeps Symfony DomCrawler's `filter()` and adds `parse()`, `remove()`, `attrs()`, `texts()`, and `htmls()`
- `RunOptions` merges query, HTTP options, and before / after hooks per call; hooks never leak into the next call
- A non-`2xx` fetch throws `FetchFailed`; an invalid Pattern, configuration, or hook return value throws `InvalidPattern`
- Tasks are scheduled by cron expression; a Task run crawls outside the database transaction, then commits its status, run dates, and Records together
- `crawler:record` queues one consumption job per pending Record; a failed consumption stays pending and writes the error to `crawl_records.exception`

## Features

- **Pattern driven**: inline patterns, file source, database source, and RSS
- **Expressive rules**: `[selector, attribute]`, `first` / `last` / numeric positions, closure transforms, and dot-notation nested fields
- **Page parsing**: built on Symfony DomCrawler with `filter()`, `parse()`, `remove()`, `attrs()`, `texts()`, and `htmls()`
- **Request control**: `RunOptions` merges pattern and per-call query / options, plus before / after hooks
- **Fetch seam**: the `Contracts\Fetcher` port with a built-in `Http\HttpFetcher`; bind your own adapter in tests
- **Consumption seam**: the `Contracts\ConsumeService` port; `Concerns\Consumable` resolves the callback named by the pattern's `consume` key
- **Task scheduling**: `crawler:task` dispatches due, active Tasks by cron expression
- **Source sync**: `crawler:task --action=import/export` mirrors Patterns between the file and database sources
- **Structured debug logs**: `crawler.fetch.debug` logs URL, query, redacted options, status, duration, response headers, and body length
- **JS rendering**: `Crawler::chrome()` renders through the `Contracts\Browser` port; `crawler:server` starts `chromedriver`

## Requirements

- PHP `^8.4` with `ext-dom`
- A Laravel application; the service provider and `Crawler` alias are registered through Laravel Package Discovery
- `chromedriver` on the machine when you use `Crawler::chrome()` or `crawler:server`
- A queue worker when you use tasks and records

Dependency versions:

| Dependency | Version |
| --- | --- |
| PHP | ^8.4 |
| ext-dom | * |
| symfony/dom-crawler | ^8.1 |
| php-webdriver/webdriver | ^1.16 |

## Install

```shell
composer require jiannei/laravel-crawler -vvv
```

Inline patterns and direct `fetch()` calls do not need a database. Publish the config, migrations, and starter Pattern file when you want stored tasks, records, or the database source:

```shell
php artisan vendor:publish --tag=crawler
php artisan migrate
```

`vendor:publish --tag=crawler` publishes:

- `config/crawler.php`
- `database/migrations/*_create_crawl_tasks_table.php`
- `database/migrations/*_create_crawl_records_table.php`
- `resources/crawler.json`

## Configuration

### Manual registration (optional)

If Laravel Package Discovery is disabled, register the service provider and alias manually:

```php
// config/app.php
'providers' => [
    Jiannei\LaravelCrawler\Providers\LaravelServiceProvider::class,
],

'aliases' => [
    'Crawler' => Jiannei\LaravelCrawler\Facades\Crawler::class,
],
```

### Environment variables

```dotenv
CRAWLER_SOURCE_CHANNEL=file
CRAWLER_LOG_LEVEL=debug
```

### Configuration reference

| Key | Default | Purpose |
| --- | --- | --- |
| `crawler.fetch.debug` | `false` | Log one structured summary per fetch. |
| `crawler.source.default` | `env('CRAWLER_SOURCE_CHANNEL', 'file')` | Active Pattern source channel. |
| `crawler.source.channels` | `file`, `database` | Source drivers and their options. |
| `crawler.consume.service` | `''` | A `ConsumeService` implementation; leave empty when this app only crawls. |
| `crawler.log` | Daily file at `storage/logs/crawler.log` | The `crawler` logging channel. |
| `crawler.guzzle.options` | Timeouts and headers | Passed to Laravel's HTTP client / Guzzle. |
| `crawler.chrome` | server, binary, arguments, wait, log | Chrome rendering and `crawler:server` settings. |
| `crawler.rss` | `channel` and `items` rules | Default pattern for `Crawler::rss()`. |

## Usage

The package ships with a comprehensive test suite under [tests](https://github.com/jiannei/laravel-crawler/tree/main/tests). The examples below cover the main paths.

### Run an inline pattern

```php
use Jiannei\LaravelCrawler\Facades\Crawler;

$items = Crawler::pattern([
    'url' => 'https://github.com/trending',
    'query' => ['since' => 'daily'],
    'group' => [
        'selector' => 'article',
        'rules' => [
            'repo' => ['h1 a', 'href'],
            'description' => ['p', 'text'],
        ],
    ],
]);

// Illuminate\Support\Collection of arrays
$items->first()['repo'];
```

Equivalent JSON structure:

```json
[
  {
    "repo": "/jiannei/laravel-crawler",
    "description": "A pattern-driven crawler for Laravel."
  }
]
```

### Fetch a page and parse it

```php
$page = Crawler::fetch('https://example.com', ['page' => 2], ['timeout' => 10]);

$title = $page->filter('h1')->text();

$items = $page->filter('.item')->parse([
    'title' => ['h2', 'text'],
    'link' => ['a', 'href'],
]);
```

`Crawler::fetch()` returns `Jiannei\LaravelCrawler\Parsing\Page`. It throws `Jiannei\LaravelCrawler\Exceptions\FetchFailed` when the response is not `2xx`.

### Build a page from HTML

```php
$page = Crawler::new('<html><body><h1>Hello</h1></body></html>');

$title = $page->filter('h1')->text();
```

`Page` adds `parse()`, `remove()`, `attrs()`, `texts()`, and `htmls()` on top of Symfony DomCrawler:

```php
$hrefs = $page->filter('a')->attrs('href');
$headings = $page->filter('h2')->texts();
$html = $page->filter('#content')->remove([
    '.ad',
    ['p', 'outerHtml', 'last'],
]);
```

### Run a stored pattern

Stored patterns live in the channel named by `crawler.source.default`. The default `file` channel reads `resources/crawler.json`:

```json
[
  {
    "key": "github:trending",
    "url": "https://github.com/trending",
    "expression": "0 * * * *",
    "query": { "since": "daily" },
    "group": {
      "selector": "article",
      "rules": {
        "repo": ["h1 a", "href"],
        "description": ["p", "text"]
      }
    }
  }
]
```

```php
$items = Crawler::json('github:trending');
```

`Crawler::json($key)` throws `InvalidPattern` when the key is unknown. A stored pattern with a non-empty `rss` key runs through the RSS parser.

### Parse an RSS feed

```php
$feed = Crawler::rss('https://example.com/feed.xml');

$feed->get('channel'); // ['title' => ..., 'link' => ...]
$feed->get('items');   // list of item arrays
```

Equivalent JSON structure:

```json
{
  "channel": {
    "title": "Example feed",
    "link": "https://example.com"
  },
  "items": [
    { "title": "First post", "link": "https://example.com/1" }
  ]
}
```

Pass a custom pattern as the second argument when the feed shape differs:

```php
$feed = Crawler::rss('https://example.com/feed.xml', [
    [
        'alias' => 'channel',
        'selector' => 'channel',
        'rules' => [
            'title' => ['title', 'text'],
            'link' => ['link', 'text'],
        ],
    ],
    [
        'alias' => 'items',
        'selector' => 'channel item',
        'rules' => [
            'title' => ['title', 'text'],
            'link' => ['link', 'text'],
            'pubDate' => ['pubDate', 'text'],
        ],
    ],
]);
```

The default RSS pattern is `crawler.rss`.

## Pattern Reference

### Rules

A rule is either a string literal or an array:

```php
'rules' => [
    // literal value
    'source' => 'github',

    // [selector, attribute]
    'title' => ['h1', 'text'],
    'link' => ['h1 a', 'href'],

    // [selector, attribute, position]
    'first_paragraph' => ['p', 'text', 'first'],
    'last_paragraph' => ['p', 'html', 'last'],
    'second_item' => ['li', 'text', 1],

    // [selector, attribute, position, closure]
    'slug' => ['h1', 'text', 'first', function ($node, $value) {
        return (string) $value->slug();
    }],

    // dot notation builds nested arrays
    'meta.author' => ['#author', 'text'],
],
```

- `selector` uses Symfony DomCrawler CSS selectors
- `attribute` is `text`, `html`, `outerHtml`, or any DOM attribute such as `href` or `src`
- `position` accepts an integer, `first`, or `last`. Omit it for the first match
- The closure receives the current item node and the extracted value as an `Illuminate\Support\Stringable`
- A rule whose selector matches nothing produces `null`

### Group shapes

Patterns support four shapes.

Ungrouped — one item for the page:

```php
[
    'url' => 'https://example.com/article',
    'rules' => [
        'title' => ['h1', 'text'],
        'body' => ['#content', 'html'],
    ],
]
```

Single group — one item per match:

```php
[
    'url' => 'https://example.com/list',
    'group' => [
        'selector' => '.item',
        'rules' => [
            'title' => ['h2 a', 'text'],
            'link' => ['h2 a', 'href'],
        ],
    ],
]
```

Multiple groups — keyed by `alias`; each value is a collection:

```php
[
    'url' => 'https://example.com/page',
    'group' => [
        [
            'alias' => 'list',
            'selector' => '.item',
            'rules' => ['title' => ['h2 a', 'text']],
        ],
        [
            'alias' => 'single',
            'selector' => '#single',
            'rules' => ['title' => ['#title', 'text']],
        ],
    ],
]
```

RSS — a list of groups; the `channel` alias resolves to one item, other aliases resolve to arrays:

```php
[
    'url' => 'https://example.com/feed.xml',
    'rss' => true,
    'group' => [
        [
            'alias' => 'channel',
            'selector' => 'channel',
            'rules' => ['title' => ['title', 'text']],
        ],
        [
            'alias' => 'items',
            'selector' => 'channel item',
            'rules' => ['title' => ['title', 'text']],
        ],
    ],
]
```

### Stored pattern keys

- `key`: required, unique
- `expression`: cron expression; optional for the file source, defaults to `* * * * *` when imported into the database source
- `consume`: the consume callback method name; leave empty or omit it to use `defaultCallback`

## Runtime Options and Hooks

Per-call inputs and hooks go through `Jiannei\LaravelCrawler\Data\RunOptions`:

```php
use Jiannei\LaravelCrawler\Data\FetchResponse;
use Jiannei\LaravelCrawler\Data\RunOptions;

$items = Crawler::json('github:trending', new RunOptions(
    query: ['spoken_language_code' => 'zh'],
    options: ['timeout' => 10],
    before: function (string $url, array $query, array $options) {
        return [$url, $query + ['since' => 'weekly'], $options];
    },
    after: function (FetchResponse $response) {
        return $response;
    },
));
```

The pattern's `query` and `options` are merged first, then the per-call `RunOptions` values are merged on top.

- `before(string $url, array $query, array $options): array` must return `[$url, $query, $options]`
- `after(FetchResponse $response): FetchResponse` must return a `FetchResponse`
- An invalid hook return value throws `InvalidPattern`

## Custom HTTP Fetcher

Fetching goes through `Jiannei\LaravelCrawler\Contracts\Fetcher`. Bind your own adapter to fake HTTP in tests or change the transport:

```php
namespace App\Crawlers;

use Jiannei\LaravelCrawler\Contracts\Fetcher;
use Jiannei\LaravelCrawler\Data\FetchResponse;

class FakeFetcher implements Fetcher
{
    public function fetch(string $url, array|string|null $query = null, array $options = []): FetchResponse
    {
        return new FetchResponse(200, '<html><body><h1>Fake</h1></body></html>');
    }
}
```

```php
$this->app->instance(Fetcher::class, new FakeFetcher());
```

The default adapter is `Jiannei\LaravelCrawler\Http\HttpFetcher`, which uses Laravel's HTTP client and reads `crawler.guzzle.options`.

## Records and Consumption

A task run stores each crawled item as a `CrawlRecord`. `crawler:record` queues one consumption job per pending record:

```shell
php artisan crawler:record
```

Set `crawler.consume.service` to a class implementing `Jiannei\LaravelCrawler\Contracts\ConsumeService`:

```php
namespace App\Crawlers;

use Jiannei\LaravelCrawler\Concerns\Consumable;
use Jiannei\LaravelCrawler\Contracts\ConsumeService;
use Jiannei\LaravelCrawler\Models\CrawlRecord;
use Jiannei\LaravelCrawler\Models\CrawlTask;

class TrendingConsumer implements ConsumeService
{
    use Consumable;

    protected function storeTrending(): callable
    {
        return function (CrawlRecord $record, CrawlTask $task): bool {
            TrendingRepository::updateOrCreate(
                ['url' => $record->content['repo']],
                ['description' => $record->content['description'] ?? null],
            );

            return true;
        };
    }

    protected function defaultCallback(): callable
    {
        return fn (CrawlRecord $record, CrawlTask $task): bool => true;
    }
}
```

```php
// config/crawler.php
'consume' => [
    'service' => App\Crawlers\TrendingConsumer::class,
],
```

`consume()` return values:

- `true`: the record is marked consumed and any previous exception is cleared
- `false`: the record stays pending without an exception, so it is retried later
- A thrown exception: the callback transaction rolls back, the message is stored in `crawl_records.exception`, and the record stays pending

When a class uses `Consumable`, the pattern's `consume` key selects the callback method. An empty or missing key uses `defaultCallback`.

## Scheduling

`crawler:task` dispatches `TaskRun` jobs for active, due tasks. `crawler:record` dispatches `RecordConsume` jobs. Schedule both and run a queue worker:

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

Schedule::command('crawler:task')->everyMinute()->withoutOverlapping();
Schedule::command('crawler:record')->everyMinute()->withoutOverlapping();
```

```shell
php artisan queue:work
```

## Commands

| Command | Description |
| --- | --- |
| `php artisan crawler:task` | Dispatch due, active tasks. |
| `php artisan crawler:task {name}` | Dispatch one named task when it is due. |
| `php artisan crawler:task --action=import` | Mirror the file source into the database source. Missing tasks are deactivated. |
| `php artisan crawler:task --action=export` | Mirror the active database source into the file source. |
| `php artisan crawler:record` | Dispatch one consumption job per pending record. |
| `php artisan crawler:record {name} --limit=500` | Limit consumption to one task and the number of records processed per run. |
| `php artisan crawler:server` | Start the configured `chromedriver`. |
| `php artisan crawler:server --path=/usr/local/bin` | Prefix the `chromedriver` binary with a directory. |

> `crawler:server` first stops any running `chromedriver` process, then starts a new one from `crawler.chrome`.

## Pattern Sources

The default source channel is `file`:

```php
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
```

- `file` reads and writes a JSON array keyed by each pattern's `key`
- `database` reads active `CrawlTask` rows and upserts by `name`
- Set `CRAWLER_SOURCE_CHANNEL=database` in production, and use `--action=import` / `--action=export` to sync between the two

## Database Tables

The published migrations create two tables:

| Table | Columns |
| --- | --- |
| `crawl_tasks` | `name` (unique), `expression` (cron), `active`, `status`, `pattern` (JSON), `exception`, `previous_run_date`, `next_run_date`, timestamps |
| `crawl_records` | `task_id`, `consumed`, `content` (JSON), `exception`, timestamps |

A task is due when it is `active` and its `next_run_date` is `null` or in the past. Imported tasks start with a `null` `next_run_date`, so they are picked up by the next `crawler:task` run. `status` uses `Jiannei\LaravelCrawler\Enums\TaskStatus` (`Pending`, `Succeeded`, `Failed`).

## JS-rendered Pages

Use `Crawler::chrome()` to render a page through Chrome and wrap the resulting HTML in a `Page`:

```php
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Jiannei\LaravelCrawler\Facades\Crawler;

$page = Crawler::chrome(
    'https://example.com/spa',
    WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('#app')),
);

$title = $page->filter('h1')->text();
```

Start a local `chromedriver`:

```shell
php artisan crawler:server
```

Browser settings live under `crawler.chrome`. `crawler.chrome.server.url` must contain the scheme and host only; the port lives in `crawler.chrome.server.port`.

## Debug Logging

Set `crawler.fetch.debug` to `true` to log one structured summary per crawler fetch to the `crawler` channel:

- URL, query, and redacted HTTP options
- Response status, duration, headers, and body length

Sensitive option keys such as `authorization`, `cookie`, `token`, `password`, and `secret` are redacted. Response bodies are not logged. `crawler.guzzle.options.debug` is a separate Guzzle debug option.

The `crawler` log channel is built from `crawler.log` and defaults to a daily file at `storage/logs/crawler.log`.

## License

The MIT License (MIT). Please see the [License File](LICENSE) for more information.
