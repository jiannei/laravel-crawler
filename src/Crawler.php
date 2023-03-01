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
        $response = $this->client($options)->get($url, $query);

        return $this->new($response->body());
    }

    /**
     * 获取元素的属性值
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
     * 解析多个元素.
     */
    public function parse(array $rules): Collection
    {
        return collect($this->each(function (SymfonyCrawler $node) use ($rules) {
            $item = [];
            foreach ($rules as $field => $rule) {
                if (is_string($rule)) {
                    Arr::set($item, $field, $rule);
                    continue;
                }

                if (!is_array($rule) || count($rule) < 2) {
                    throw new \InvalidArgumentException("The [$field] rule is invalid.");
                }

                $parsed = $this->parseRule($rule, $node);

                Arr::set($item, $field, count($parsed) > 1 ? $parsed : head($parsed));
            }

            return $item;
        }));
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
    protected function parseRule(array $rule, SymfonyCrawler $node = null): array
    {
        // [selector,attribute, position,callback]
        @list($selector, $attribute, $position, $closure) = $rule;

        $crawler = $node ?? $this;

        $elements = $crawler->filter($selector);

        if ('first' === $position) {
            $position = 0;
        } elseif ('last' === $position) {
            $position = $elements->count() - 1;
        }

        if (!is_null($position)) {
            $elements = $elements->eq($position);
        }

        return $elements->each(function (SymfonyCrawler $node,$i) use ($crawler,$position, $attribute, $closure) {
            if (in_array($attribute, ['text', 'html', 'outerHtml'])) {
                $result = $node->$attribute();
            } else {
                $result = $node->attr($attribute);
            }

            return is_callable($closure) ? $closure($crawler, Str::of($result)) : $result;
        });
    }
}
