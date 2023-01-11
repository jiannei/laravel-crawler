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
     * 获取远程html后构建爬虫对象
     *
     * @param array|string|null $query
     *
     * @return $this
     */
    public function fetch(string $url, array|string|null $query = null): static
    {
        $html = Http::withOptions(config('crawler.http.options', []))->get(...func_get_args())->body();

        return $this->new($html);
    }

    /**
     * 获取元素的属性值
     */
    public function attrs(string|array $attribute): array
    {
        return $this->extract(Arr::wrap($attribute));
    }

    /**
     * 获取多元素的文本内容.
     */
    public function texts(): array
    {
        return $this->each(function (SymfonyCrawler $node) {
            return $node->text();
        });
    }

    /**
     * 获取多元素自身html.
     */
    public function htmls(): array
    {
        return $this->each(function (SymfonyCrawler $node) {
            return $node->html();
        });
    }

    /**
     * 组合式获取内容.
     */
    public function rules(array $rules): array
    {
        return $this->each(function (SymfonyCrawler $node) use ($rules) {
            $item = [];
            foreach ($rules as $field => $rule) {
                if (!is_array($rule) || 2 !== count($rule)) {
                    throw new \InvalidArgumentException("The [$field] rule is invalid.");
                }

                $selectors = explode(';', $rule[0]); // todo
                $method = $rule[1];

                $position = '';
                $selector = $selectors[0];
                if (count($selectors) > 1 && in_array($selectors[1], ['first', 'last'])) {
                    $position = $selectors[1];
                }

                $item[$field] = null;
                if ($node->filter($selector)->count()) {
                    if (!$position) {
                        $item[$field] = !in_array($method, ['text', 'html', 'outerHtml']) ? $node->filter($selector)->attr($method) : $node->filter($selector)->$method();
                    } else {
                        $item[$field] = !in_array($method, ['text', 'html', 'outerHtml']) ? $node->filter($selector)->$position()->attr($method) : $node->filter($selector)->$position()->$method();
                    }
                }
            }

            return $item;
        });
    }

    /**
     * 移除指定元素.
     */
    public function remove(string|array $patterns): string
    {
        $rules = $this->patternToRule($patterns);

        $html = $this->html();
        foreach ($this->rules($rules) as $items) {
            $html = Str::remove($items, $html);
        }

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
     * 移除/替换/追加操作规则解析.
     */
    protected function patternToRule(string|array $patterns): array
    {
        $patterns = Arr::wrap($patterns);

        $rules = [];
        foreach ($patterns as $pattern) {
            $rules[] = [$pattern, 'outerHtml'];
        }

        return $rules;
    }
}
