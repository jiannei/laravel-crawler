<?php

/*
 * This file is part of the jiannei/laravel-crawler.
 *
 * (c) jiannei <longjian.huang@foxmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Jiannei\LaravelCrawler;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Jiannei\LaravelCrawler\Models\CrawlTask;
use Symfony\Component\DomCrawler\Crawler as SymfonyCrawler;

class Crawler extends SymfonyCrawler
{
    private static bool $grouped = false;
    private static $beforeClosure;

    private static $afterClosure;

    /**
     * Build a new crawler object.
     *
     * @return $this
     */
    public function new(\DOMNodeList|\DOMNode|array|string $node = null, string $uri = null, string $baseHref = null): static
    {
        return new static(...func_get_args());
    }

    /**
     * Http Client.
     *
     * @return \Illuminate\Http\Client\PendingRequest
     */
    public function client(array $options = [])
    {
        $options = array_merge(config('crawler.guzzle.options', []), $options);

        return Http::throw()->withOptions($options);
    }

    /**
     * Build crawler object after getting remote content.
     */
    public function fetch(string $url, array|string|null $query = null, array $options = []): static
    {
        [$url,$query,$options] = $this->beforeFetch(func_get_args());

        $response = $this->afterFetch($this->client($options)->get($url, $query));

        return $this->new($response->body());
    }

    /**
     * More concise crawling way.
     */
    public function pattern(array $pattern): array|Collection
    {
        $crawler = $this->fetch($pattern['url'], $pattern['query'] ?? '', $pattern['options'] ?? []);
        $group = $pattern['group'] ?? [];
        $multiGroup = Arr::isList($group);

        $data = collect();
        if ($group) {
            foreach (!$multiGroup ? [$group] : $group as $key => $item) {
                $data->put($item['alias'] ?? $key, $crawler->group($item['selector'])->parse($item['rules']));
            }
        } else {
            $data->push($crawler->parse($pattern['rules']));
        }

        return (!$multiGroup || empty($group)) ? $data->first() : $data;
    }

    /**
     * Return the parsing result according to the url matching rule.
     */
    public function json(string $key, array $query = [], array $options = []): array|Collection
    {
        $source = $this->source(config('crawler.source.default', 'file'))->keyBy('key');
        if (!$source->has($key)) {
            throw new \InvalidArgumentException('url pattern not exist');
        }

        $pattern = $source->get($key);

        Arr::set($pattern, 'query', array_merge(Arr::get($pattern, 'query', []), $query));
        Arr::set($pattern, 'options', array_merge(Arr::get($pattern, 'options', []), $options));

        return Arr::get($pattern, 'rss', false) ? $this->rss($pattern['url']) : $this->pattern($pattern);
    }

    /**
     * Get crawler pattern rules by source.
     */
    public function source(string $source = 'file', array $patterns = []): Collection|int
    {
        if (!in_array($source, ['file', 'database'])) {
            throw new \InvalidArgumentException('source illegal');
        }

        $sourceAction = ($patterns ? 'set' : 'get').ucfirst($source).'Source';

        return match ($sourceAction) {
            default => $this->getFileSource(),
            'setFileSource' => $this->setFileSource($patterns),
            'getDatabaseSource' => $this->getDatabaseSource(),
            'setDatabaseSource' => $this->setDatabaseSource($patterns)
        };
    }

    /**
     * Parse RSS rules.
     */
    public function rss(string $url, array $pattern = []): Collection
    {
        return $this->pattern(['url' => $url, 'group' => $pattern ?: config('crawler.rss')])
            ->map(function (Collection $item, $key) {
                return 'channel' === $key ? $item->first() : $item->all();
            });
    }

    public function before(\Closure $closure): static
    {
        self::$beforeClosure = $closure;

        return $this;
    }

    public function after(\Closure $closure): static
    {
        self::$afterClosure = $closure;

        return $this;
    }

    /**
     * Get the attribute value of the element.
     */
    public function attrs(string|array $attribute): array
    {
        return $this->extract(Arr::wrap($attribute));
    }

    /**
     * Get the text content of the element.
     */
    public function texts(): array
    {
        return $this->each(function (SymfonyCrawler $node) {
            return $node->text();
        });
    }

    /**
     * Get the element's html.
     */
    public function htmls(): array
    {
        return $this->each(function (SymfonyCrawler $node) {
            return $node->html();
        });
    }

    /**
     * Group matching rules.
     */
    public function group(string $selector): static
    {
        $this->setGroupFlag(true);

        return $this->filter($selector);
    }

    /**
     * Parse multiple elements.
     */
    public function parse(array $rules): array|Collection
    {
        $data = $this->each(function (SymfonyCrawler $node) use ($rules) {
            $item = [];
            foreach ($rules as $field => $rule) {
                if (is_string($rule)) {
                    Arr::set($item, $field, $rule);
                    continue;
                }

                if (!is_array($rule) || count($rule) < 2) {
                    throw new \InvalidArgumentException("The [$field] rule is invalid.");
                }

                // [selector,attribute, position,callback]
                @list($selector, $attribute, $position, $closure) = $rule;

                $element = $node->filter($selector);
                if (!$element->count()) {
                    Arr::set($item, $field, null);
                    continue;
                }

                if ('first' === $position) {
                    $position = 0;
                } elseif ('last' === $position) {
                    $position = $element->count() - 1;
                }

                if (!is_null($position)) {
                    $element = $element->eq($position);
                }

                if (in_array($attribute, ['text', 'html', 'outerHtml'])) {
                    $result = $element->$attribute();
                } else {
                    $result = $element->attr($attribute);
                }

                $result = is_callable($closure) ? $closure($node, Str::of($result)) : $result;

                Arr::set($item, $field, $result);
            }

            return $item;
        });

        return $this->getGroupFlag() ? collect($data) : head($data);
    }

    /**
     * Get JS rendering page.
     *
     * @return $this
     *
     * @throws \Facebook\WebDriver\Exception\NoSuchElementException
     * @throws \Facebook\WebDriver\Exception\TimeoutException
     * @throws \Facebook\WebDriver\Exception\UnsupportedOperationException
     */
    public function chrome(string $url, WebDriverExpectedCondition $condition)
    {
        $driverConfig = config('crawler.chrome');

        $desiredCapabilities = DesiredCapabilities::chrome();

        $chromeOptions = new ChromeOptions();
        $chromeOptions->addArguments(Arr::get($driverConfig, 'arguments'));
        $desiredCapabilities->setCapability(ChromeOptions::CAPABILITY, $chromeOptions);

        $serverUrl = Arr::get($driverConfig, 'server.url').':'.Arr::get($driverConfig, 'server.port');
        $driver = RemoteWebDriver::create($serverUrl, $desiredCapabilities);
        $driver->get($url);

        $driver->wait(Arr::get($driverConfig, 'wait.timeout_in_second'), Arr::get($driverConfig, 'wait.interval_in_millisecond'))
            ->until($condition);

        $html = $driver->findElement(WebDriverBy::cssSelector('html'))->getDomProperty('innerHTML');

        $driver->quit();

        return $this->new($html);
    }

    /**
     * Remove Elements.
     */
    public function remove(string|array $rules): string
    {
        $html = $this->html();
        foreach ($this->parse($rules) as $items) {
            $html = Str::remove($items, $html);
        }

        return $html;
    }

    /**
     * Set rule grouping flag.
     */
    protected function setGroupFlag(bool $flag): void
    {
        self::$grouped = $flag;
    }

    /**
     * Get rule grouping flag.
     */
    protected function getGroupFlag(): bool
    {
        return self::$grouped;
    }

    protected function beforeFetch(array $args): array
    {
        $args = array_pad($args, 3, []);

        if (is_callable(self::$beforeClosure)) {
            $args = (self::$beforeClosure)(...$args);
        }

        return $args;
    }

    protected function afterFetch(Response $response)
    {
        if (is_callable(self::$afterClosure)) {
            $response = (self::$afterClosure)($response);
        }

        $this->setGroupFlag(false);

        self::$beforeClosure = null;
        self::$afterClosure = null;

        return $response;
    }

    protected function getFileSource(): Collection
    {
        if (!File::exists(config('crawler.source.channels.file'))) {
            throw new \InvalidArgumentException('source config illegal');
        }

        return collect(json_decode(File::get(config('crawler.source.channels.file')), true));
    }

    protected function setFileSource(array $patterns): bool|int
    {
        return File::put(config('crawler.source.channels.file'),
            json_encode($patterns, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
    }

    protected function getDatabaseSource()
    {
        return CrawlTask::active()->select('pattern')->get()->pluck('pattern');
    }

    protected function setDatabaseSource(array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            $data = [
                'name' => $pattern['key'],
                'expression' => $pattern['expression'] ?? '* * * * *',
                'pattern' => $pattern,
            ];

            CrawlTask::updateOrCreate(['name' => $data['name']], $data);
        }

        return true;
    }
}
