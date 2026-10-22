<?php

/*
 * This file is part of the jiannei/laravel-crawler.
 *
 * (c) jiannei <jiannei@sinan.fun>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Jiannei\LaravelCrawler\Parsing;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler as SymfonyCrawler;

class Page extends SymfonyCrawler
{
    /**
     * Build a new page object.
     *
     * @return $this
     */
    public function new(\DOMNodeList|\DOMNode|array|string|null $node = null, ?string $uri = null, ?string $baseHref = null): static
    {
        return new static(...func_get_args());
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
     * Parse every selected node into an item.
     */
    public function parse(array $rules): Collection
    {
        return collect(RuleParser::parseMany($this, $rules));
    }

    /**
     * Remove Elements.
     */
    public function remove(string|array $rules): string
    {
        $html = $this->html();

        foreach ($this->parse($rules) as $item) {
            $html = Str::remove($item, $html);
        }

        return $html;
    }
}
