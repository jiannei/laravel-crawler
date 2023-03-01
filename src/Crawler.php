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

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler as SymfonyCrawler;

class Crawler extends SymfonyCrawler
{
    private static bool $groupped = false;

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
     *
     * @param array|string|null $query
     *
     * @return $this
     */
    public function fetch(string $url, array|string|null $query = null, array $options = []): static
    {
        $this->setGroupFlag(false);

        $response = $this->client($options)->get($url, $query);

        return $this->new($response->body());
    }

    /**
     * 更简洁的爬取方式.
     */
    public function pattern(array $pattern,string|array $fields = ''): array|Collection
    {
        $crawler = $this->fetch($pattern['url'], $pattern['query'] ?? '', $pattern['options'] ?? []);
        $group = $pattern['group'] ?? [];

        $data = collect();
        if ($group) {
            foreach (Arr::wrap($group) as $selector => $rules) {
                $value = !is_string($selector) ?
                    $crawler->group($rules)->parse($pattern['rules']) :
                    $crawler->group($selector)->parse($rules);

                $data->offsetSet($selector,$value);
            }
        }else{
            $data->push($crawler->parse($pattern['rules']));
        }

        return (is_string($group) || empty($group)) ? $data->first() : collect(Arr::wrap($fields))->combine($data->all());
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

                Arr::set($item, $field, $this->parseRule($rule, $node));
            }

            return $item;
        });

        return $this->getGroupFlag() ? collect($data) : head($data);
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
     * 根据规则解析元素.
     */
    protected function parseRule(array $rule, SymfonyCrawler $node = null): ?string
    {
        // [selector,attribute, position,callback]
        @list($selector, $attribute, $position, $closure) = $rule;

        $crawler = $node ?? $this;

        $element = $crawler->filter($selector);
        if (!$element->count()) {
            return null;
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

        return is_callable($closure) ? $closure($crawler, Str::of($result)) : $result;
    }

    /**
     * 设置规则分组标识.
     */
    protected function setGroupFlag(bool $flag): void
    {
        self::$groupped = $flag;
    }

    /**
     * 获取规则分组标识.
     */
    protected function getGroupFlag(): bool
    {
        return self::$groupped;
    }
}
