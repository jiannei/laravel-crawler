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
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\ArrayToXml\ArrayToXml;
use Symfony\Component\DomCrawler\Crawler as SymfonyCrawler;

class Crawler extends SymfonyCrawler
{
    private static bool $grouped = false;
    private static string $contentType = 'html';

    /**
     * 构建一个新的爬虫对象
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
     * 获取远程html后构建爬虫对象
     */
    public function fetch(string $url, array|string|null $query = null, array $options = []): static
    {
        $this->setGroupFlag(false);

        $response = $this->client($options)->get($url, $query);

        return self::$contentType !== 'xml' ? $this->new($response->body()) : $this->new(ArrayToXml::convert($response->json()));
    }

    public function contentHtml(): static
    {
        self::$contentType = 'html';

        return $this;
    }

    public function contentXml(): static
    {
        self::$contentType = 'xml';

        return $this;
    }

    /**
     * 更简洁的爬取方式.
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
     * 根据 url 匹配规则，返回解析结果.
     */
    public function json(string $url, string $source = 'storage'): array|Collection
    {
        if (!File::exists(config('crawler.source.storage'))) {
            throw new \InvalidArgumentException('source config illegal');
        }

        $source = collect(json_decode(File::get(config('crawler.source.storage')), true))->keyBy('url');
        if (!$source->has($url)) {
            throw new \InvalidArgumentException('url pattern not exist');
        }

        return $this->pattern($source->get($url));
    }

    /**
     * 解析 RSS 规则.
     */
    public function rss(string $url): Collection
    {
        return $this->pattern(['url' => $url, 'group' => config('crawler.rss')])
            ->map(function (Collection $item, $key) {
                return 'channel' === $key ? $item->first() : $item->all();
            });
    }

    /**
     * 获取元素的属性值.
     */
    public function attrs(string|array $attribute): array
    {
        return $this->extract(Arr::wrap($attribute));
    }

    /**
     * 获取元素的文本内容.
     */
    public function texts(): array
    {
        return $this->each(function (SymfonyCrawler $node) {
            return $node->text();
        });
    }

    /**
     * 获取元素自身html.
     */
    public function htmls(): array
    {
        return $this->each(function (SymfonyCrawler $node) {
            return $node->html();
        });
    }

    /**
     * 规则分组.
     */
    public function group(string $selector): static
    {
        $this->setGroupFlag(true);

        return $this->filter($selector);
    }

    /**
     * 解析多个元素.
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
     * 获取JS渲染页面.
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
     * 移除元素.
     */
    public function remove(string|array $rules): string
    {
        $html = $this->html();
        foreach ($this->parse($rules) as $items) {
            $html = Str::remove($items, $html);
        }

        // TODO 连贯操作 rules()->remove()
        // TODO 移除指定元素属性、元素文本内容(removeHtml/removeText/removeAttr)
        /*[
            '.tt' => Element::TEXT,
            'span:last' => Element::HTML,
            'p:last' => Element::HTML,
            'a' => ['href']
        ]*/

        return $html;
    }

    /**
     * 设置规则分组标识.
     */
    protected function setGroupFlag(bool $flag): void
    {
        self::$grouped = $flag;
    }

    /**
     * 获取规则分组标识.
     */
    protected function getGroupFlag(): bool
    {
        return self::$grouped;
    }
}
