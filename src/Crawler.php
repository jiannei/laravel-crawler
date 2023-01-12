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
use Illuminate\Support\Carbon;
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
     * @param  string  $url
     * @param  array|string|null  $query
     * @param  array  $options
     * @return $this
     */
    public function fetch(string $url, array|string|null $query = null, array $options = []): static
    {
        $options = array_merge(config('crawler.http.options', []),$options);
        if (config('crawler.debug', false) && !isset($options['debug'])) {
            $suffix = Carbon::now()->format('Y-m-d');
            $options['debug'] = fopen(storage_path("logs/crawler-{$suffix}.log"), 'a+');
        }

        $html = Http::withOptions($options)->get(...func_get_args())->body();

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
                // [selector,attribute, pseudo-class]
                // [selector,attribute, position]

                if (!is_array($rule) || count($rule) < 2) {
                    throw new \InvalidArgumentException("The [$field] rule is invalid.");
                }

                @list($selector,$attribute,$position) = $rule;

                $element = $node->filter($selector);

                $item[$field] = null;
                if (!$element->count()) {
                    continue;
                }

                if ($position === 'first') {
                    $position = 0;
                } elseif ($position === 'last') {
                    $position =  $element->count() - 1;
                }

                if (!is_null($position)) {
                    $element = $element->eq($position);
                }

                if (in_array($attribute, ['text', 'html', 'outerHtml'])) {
                    $item[$field] = $element->$attribute();
                }else{
                    $item[$field] = $element->attr($attribute);
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
        $html = $this->html();
        foreach ($this->rules($patterns) as $items) {
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
     * 移除/替换/追加操作规则解析.
     *
     * @param  string|array  $patterns
     * @return array
     */
    protected function patternToRule(string|array $patterns): array
    {
        $patterns = Arr::wrap($patterns);

        $rules = [];
        foreach ($patterns as $index => $pattern) {
            $rules[] = is_numeric($index) ? [$pattern, 'outerHtml',null] : [$index, 'outerHtml',$pattern];
        }

        return $rules;
    }
}
