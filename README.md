<h1 align="center"> laravel-crawler </h1>

[简体中文](README.md) | [ENGLISH](README-EN.md)

> 一个基于 Pattern 的 Laravel 爬虫包。

> **📝 版本说明：** 当前文档对应 v3 开发版。v3 按 Laravel 通用目录重新组织，公开命名空间与 v2 不兼容，文档中的类名均以 v3 为准。

[![Test](https://github.com/jiannei/laravel-crawler/actions/workflows/test.yml/badge.svg)](https://github.com/jiannei/laravel-crawler/actions/workflows/test.yml)
[![StyleCI](https://github.styleci.io/repos/585477175/shield?branch=main&style=flat)](https://github.styleci.io/repos/585477175/shield?branch=main&style=flat)
[![Pint](https://img.shields.io/badge/Code%20Style-Pint-orange?style=flat)](https://laravel.com/docs/pint)
[![Latest Stable Version](http://poser.pugx.org/jiannei/laravel-crawler/v)](https://packagist.org/packages/jiannei/laravel-crawler)
[![Total Downloads](http://poser.pugx.org/jiannei/laravel-crawler/downloads)](https://packagist.org/packages/jiannei/laravel-crawler)
[![Monthly Downloads](http://poser.pugx.org/jiannei/laravel-crawler/d/monthly)](https://packagist.org/packages/jiannei/laravel-crawler)
[![Latest Unstable Version](http://poser.pugx.org/jiannei/laravel-crawler/v/unstable)](https://packagist.org/packages/jiannei/laravel-crawler)
[![License](http://poser.pugx.org/jiannei/laravel-crawler/license)](https://packagist.org/packages/jiannei/laravel-crawler)
[![PHP Version Require](http://poser.pugx.org/jiannei/laravel-crawler/require/php)](https://packagist.org/packages/jiannei/laravel-crawler)

## 介绍

`laravel-crawler` 把「请求 → 解析 → 存储 → 消费」这条链路收进一个包。

用 Pattern 描述抓什么、怎么解析：

- Pattern 可以直接传给 `Crawler::pattern()`，也可以存到文件源或数据库源，再用 `Crawler::json()` 按 key 运行
- `Crawler::fetch()` 和 `Crawler::new()` 返回一个 `Page`，继续使用 Symfony DomCrawler 的 `filter()`，并额外提供 `parse()`、`remove()`、`attrs()`、`texts()`、`htmls()`
- `RunOptions` 在每次调用时叠加 query、HTTP options 和 before / after 钩子，钩子不会泄漏到下一次调用
- 抓取状态码非 2xx 时抛 `FetchFailed`；Pattern、配置或钩子返回值不合法时抛 `InvalidPattern`
- Task 按 cron 表达式调度；一次 Task run 的抓取在数据库事务之外执行，完成后状态、运行日期和 Records 一起提交
- `crawler:record` 为待消费的 Record 派发消费任务；消费失败保留 pending，并把异常写入 `crawl_records.exception`

## 概览

- **Pattern 驱动**：支持 inline pattern、文件源、数据库源和 RSS
- **规则表达力**：`[selector, attribute]`、位置 `first` / `last` / 数字、闭包转换、字段 dot 嵌套
- **Page 解析**：基于 Symfony DomCrawler，支持 `filter()`、`parse()`、`remove()`、`attrs()`、`texts()`、`htmls()`
- **请求控制**：`RunOptions` 合并 pattern 与每次调用的 query / options，支持 before / after 钩子
- **Fetch seam**：`Contracts\Fetcher` 端口，内置 `Http\HttpFetcher`，可在测试中绑定自己的 adapter
- **消费 seam**：`Contracts\ConsumeService` 端口，`Concerns\Consumable` 按 pattern 的 `consume` key 解析回调
- **Task 调度**：`crawler:task` 按 cron 表达式派发到期的 active Task
- **数据源同步**：`crawler:task --action=import/export` 在文件源和数据库源之间镜像 Pattern
- **结构化调试日志**：`crawler.fetch.debug` 打开后记录 URL、query、脱敏 options、状态、耗时、响应头和 body 长度
- **JS 渲染**：`Crawler::chrome()` 通过 `Contracts\Browser` 获取渲染后的 HTML，`crawler:server` 启动 `chromedriver`

## 环境要求

- PHP `^8.4`，需要 `ext-dom`
- 一个 Laravel 应用；Service Provider 和 `Crawler` alias 通过 Laravel Package Discovery 自动注册
- 使用 `Crawler::chrome()` 或 `crawler:server` 时，需要本地有 `chromedriver`
- 使用 Task / Record 时，需要运行 queue worker

依赖版本：

| 依赖 | 版本 |
| --- | --- |
| PHP | ^8.4 |
| ext-dom | * |
| symfony/dom-crawler | ^8.1 |
| php-webdriver/webdriver | ^1.16 |

## 安装

```shell
composer require jiannei/laravel-crawler -vvv
```

只运行 inline pattern 或直接 `fetch()` 时，不需要数据库。需要存储 Task、Record 或使用数据库源时，发布配置、迁移和 Pattern 文件：

```shell
php artisan vendor:publish --tag=crawler
php artisan migrate
```

`vendor:publish --tag=crawler` 会发布：

- `config/crawler.php`
- `database/migrations/*_create_crawl_tasks_table.php`
- `database/migrations/*_create_crawl_records_table.php`
- `resources/crawler.json`

## 配置

### 手动注册（可选）

如果关闭了 Laravel Package Discovery，手动注册 Service Provider 和 alias：

```php
// config/app.php
'providers' => [
    Jiannei\LaravelCrawler\Providers\LaravelServiceProvider::class,
],

'aliases' => [
    'Crawler' => Jiannei\LaravelCrawler\Facades\Crawler::class,
],
```

### 环境变量

```dotenv
CRAWLER_SOURCE_CHANNEL=file
CRAWLER_LOG_LEVEL=debug
```

### 配置项

| 配置项 | 默认值 | 说明 |
| --- | --- | --- |
| `crawler.fetch.debug` | `false` | 为每次 fetch 记录一条结构化日志。 |
| `crawler.source.default` | `env('CRAWLER_SOURCE_CHANNEL', 'file')` | 当前生效的 Pattern 数据源 channel。 |
| `crawler.source.channels` | `file`、`database` | 数据源 driver 及其参数。 |
| `crawler.consume.service` | `''` | `ConsumeService` 实现类；只抓取不消费时留空。 |
| `crawler.log` | 每日写入 `storage/logs/crawler.log` | `crawler` 日志 channel。 |
| `crawler.guzzle.options` | 超时和请求头 | 传给 Laravel HTTP client / Guzzle 的选项。 |
| `crawler.chrome` | server、binary、arguments、wait、log | Chrome 渲染与 `crawler:server` 配置。 |
| `crawler.rss` | `channel` 和 `items` 规则 | `Crawler::rss()` 的默认 Pattern。 |

## 使用

扩展包自带较完整的测试用例 [tests](https://github.com/jiannei/laravel-crawler/tree/main/tests)，也可以直接从下面的示例开始。

### 直接运行 Pattern

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

等价 JSON 结构：

```json
[
  {
    "repo": "/jiannei/laravel-crawler",
    "description": "A pattern-driven crawler for Laravel."
  }
]
```

### 抓取页面并解析

```php
$page = Crawler::fetch('https://example.com', ['page' => 2], ['timeout' => 10]);

$title = $page->filter('h1')->text();

$items = $page->filter('.item')->parse([
    'title' => ['h2', 'text'],
    'link' => ['a', 'href'],
]);
```

`Crawler::fetch()` 返回 `Jiannei\LaravelCrawler\Parsing\Page`；当响应状态码不是 `2xx` 时抛 `Jiannei\LaravelCrawler\Exceptions\FetchFailed`。

### 构建本地 Page

```php
$page = Crawler::new('<html><body><h1>Hello</h1></body></html>');

$title = $page->filter('h1')->text();
```

`Page` 在 Symfony DomCrawler 之上增加了 `parse()`、`remove()`、`attrs()`、`texts()` 和 `htmls()`：

```php
$hrefs = $page->filter('a')->attrs('href');
$headings = $page->filter('h2')->texts();
$html = $page->filter('#content')->remove([
    '.ad',
    ['p', 'outerHtml', 'last'],
]);
```

### 运行已存储 Pattern

存储的 Pattern 放在 `crawler.source.default` 指定的 channel 中。默认 `file` channel 读取 `resources/crawler.json`：

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

`Crawler::json($key)` 在 key 不存在时抛 `InvalidPattern`。如果存储的 Pattern 带有非空 `rss` key，会走 RSS 解析流程。

### 解析 RSS

```php
$feed = Crawler::rss('https://example.com/feed.xml');

$feed->get('channel'); // ['title' => ..., 'link' => ...]
$feed->get('items');   // list of item arrays
```

等价 JSON 结构：

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

当 feed 结构不同，可在第二个参数传入自定义 Pattern：

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

默认 RSS Pattern 为 `crawler.rss`。

## Pattern 参考

### 规则

一条规则可以是字符串字面量，也可以是数组：

```php
'rules' => [
    // 字面量
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

    // 字段名支持 dot 语法，会构建嵌套数组
    'meta.author' => ['#author', 'text'],
],
```

- `selector` 使用 Symfony DomCrawler 的 CSS selector 语法
- `attribute` 可以是 `text`、`html`、`outerHtml`，或其他 DOM 属性，如 `href`、`src`
- `position` 接受数字、`first` 或 `last`；省略时取第一个匹配
- 闭包接收当前 item 节点和已提取的值（`Illuminate\Support\Stringable`）
- selector 没有匹配到节点时，字段值为 `null`

### Group 形态

Pattern 支持四种形态。

无 group —— 整页产出 1 条 item：

```php
[
    'url' => 'https://example.com/article',
    'rules' => [
        'title' => ['h1', 'text'],
        'body' => ['#content', 'html'],
    ],
]
```

单个 group —— 每个匹配产出 1 条 item：

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

多个 group —— 以 `alias` 为 key，每个 value 是一个 Collection：

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

RSS —— group 需要是列表；`channel` alias 取单条，其他 alias 取数组：

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

### 存储 Pattern 的字段

- `key`：必填，唯一标识
- `expression`：cron 表达式；文件源可选，导入数据库源时默认 `* * * * *`
- `consume`：消费回调方法名；留空或省略时使用 `defaultCallback`

## 运行时选项与钩子

每次调用的输入和钩子通过 `Jiannei\LaravelCrawler\Data\RunOptions` 传入：

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

先合并 Pattern 的 `query` 和 `options`，再叠加每次调用的 `RunOptions`。

- `before(string $url, array $query, array $options): array` 必须返回 `[$url, $query, $options]`
- `after(FetchResponse $response): FetchResponse` 必须返回 `FetchResponse`
- 钩子返回值不合法时抛 `InvalidPattern`

## 自定义 HTTP Fetcher

抓取过程通过 `Jiannei\LaravelCrawler\Contracts\Fetcher` 端口完成。可以在测试里绑定自己的 adapter，或替换传输层：

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

默认 adapter 是 `Jiannei\LaravelCrawler\Http\HttpFetcher`，它使用 Laravel HTTP client，并读取 `crawler.guzzle.options`。

## Records 与消费

一次 Task run 会把每条抓取结果存成 `CrawlRecord`。`crawler:record` 为每条待消费 Record 派发一个消费任务：

```shell
php artisan crawler:record
```

把 `crawler.consume.service` 配成实现了 `Jiannei\LaravelCrawler\Contracts\ConsumeService` 的类：

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

`consume()` 的返回值语义：

- `true`：Record 标记为已消费，同时清除之前的 exception
- `false`：Record 保持 pending，且不记录 exception，等待下次重试
- 抛出异常：回调所在事务回滚，异常消息写入 `crawl_records.exception`，Record 保持 pending

使用 `Consumable` 时，Pattern 的 `consume` key 决定调用哪个方法；留空或省略时调用 `defaultCallback`。

## 调度

`crawler:task` 为到期的 active Task 派发 `TaskRun`；`crawler:record` 为待消费 Record 派发 `RecordConsume`。把它们加入调度，并运行 queue worker：

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

Schedule::command('crawler:task')->everyMinute()->withoutOverlapping();
Schedule::command('crawler:record')->everyMinute()->withoutOverlapping();
```

```shell
php artisan queue:work
```

## 命令

| 命令 | 说明 |
| --- | --- |
| `php artisan crawler:task` | 派发到期且 active 的 Task。 |
| `php artisan crawler:task {name}` | 在到期时派发指定名称的 Task。 |
| `php artisan crawler:task --action=import` | 把文件源镜像到数据库源；文件中不存在的 Task 会被置为 inactive。 |
| `php artisan crawler:task --action=export` | 把 active 的数据库源镜像到文件源。 |
| `php artisan crawler:record` | 为每条待消费 Record 派发一个消费任务。 |
| `php artisan crawler:record {name} --limit=500` | 只消费指定 Task，并限制单次处理的 Record 数量。 |
| `php artisan crawler:server` | 启动配置中的 `chromedriver`。 |
| `php artisan crawler:server --path=/usr/local/bin` | 为 `chromedriver` 二进制文件指定目录前缀。 |

> `crawler:server` 会先结束已有的 `chromedriver` 进程，再按 `crawler.chrome` 配置启动新的进程。

## Pattern 数据源

默认数据源 channel 是 `file`：

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

- `file` 读写 JSON 数组，每个 Pattern 以 `key` 作为唯一标识
- `database` 读取 active 的 `CrawlTask`，并按 `name` upsert
- 生产环境可设置 `CRAWLER_SOURCE_CHANNEL=database`，再用 `--action=import` / `--action=export` 在两个数据源之间同步

## 数据库表

发布的迁移会创建两张表：

| 表 | 字段 |
| --- | --- |
| `crawl_tasks` | `name`（唯一）、`expression`（cron）、`active`、`status`、`pattern`（JSON）、`exception`、`previous_run_date`、`next_run_date`、timestamps |
| `crawl_records` | `task_id`、`consumed`、`content`（JSON）、`exception`、timestamps |

Task 处于 active 且 `next_run_date` 为 `null` 或已过期时会被视为到期。导入进来的 Task 初始 `next_run_date` 为 `null`，会被下一次 `crawler:task` 拾取。`status` 使用 `Jiannei\LaravelCrawler\Enums\TaskStatus`（`Pending`、`Succeeded`、`Failed`）。

## JS 渲染页面

使用 `Crawler::chrome()` 通过 Chrome 渲染页面，再把渲染后的 HTML 包装成 `Page`：

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

启动本地 `chromedriver`：

```shell
php artisan crawler:server
```

浏览器相关配置在 `crawler.chrome`。`crawler.chrome.server.url` 只能包含 scheme 和 host，端口单独配置在 `crawler.chrome.server.port`。

## Debug 日志

把 `crawler.fetch.debug` 设为 `true`，每次 crawler fetch 都会向 `crawler` channel 写一条结构化日志：

- URL、query、脱敏后的 HTTP options
- 响应状态码、耗时、响应头、body 长度

`authorization`、`cookie`、`token`、`password`、`secret` 等敏感 option key 会被脱敏，响应 body 不会写入日志。`crawler.guzzle.options.debug` 是另一个独立的 Guzzle debug 选项。

`crawler` 日志 channel 由 `crawler.log` 生成，默认按天写入 `storage/logs/crawler.log`。

## 协议

MIT 许可证（MIT）。有关更多信息，请参见 [协议文件](LICENSE)。
