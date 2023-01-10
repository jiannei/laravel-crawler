<?php

namespace Jiannei\LaravelCrawler;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler as SymfonyCrawler;

class Crawler extends SymfonyCrawler
{
    public function new(\DOMNodeList|\DOMNode|array|string $node = null, string $uri = null, string $baseHref = null): static
    {
        return new static(...func_get_args());
    }

    public function fetch(string $url, array|string|null $query = null): static
    {
        $html = Http::get(...func_get_args())->body();

        return $this->new($html);
    }

    public function rules(array $rules):array
    {
        return $this->each(function (SymfonyCrawler $node) use ($rules) {
            $item = [];
            foreach ($rules as $field => $rule) {
                // Todo more check
                $selector = $rule[0];
                $method = $rule[1];

                if (!$node->filter($selector)->count()) {
                    continue;
                }

                $item[$field] = !in_array($method, ['text', 'html']) ? $node->filter($selector)->attr($method) : $node->filter($selector)->$method();
            }

            return $item;
        });
    }

    public function attrs(string $attribute):array
    {
        return $this->each(function (SymfonyCrawler $node) use ($attribute){
            return $node->attr($attribute);
        });
    }

    public function texts(): array
    {
        return $this->each(function (SymfonyCrawler $node) {
            return $node->text();
        });
    }

    public function htmls():array
    {
        return $this->each(function (SymfonyCrawler $node) {
            return $node->html();
        });
    }

    // todo 整合至 rules
    public function remove(array $rules)
    {
        $html = $this->html();
        foreach ($rules as $rule) {
            $rule = explode(':', $rule);
            $selector = $rule[0];
            $position = $rule[1] ?? '';

            if (!$this->filter($selector)->count()) {
                continue;
            }

            if (!in_array($position, ['first', 'last'])) {
                $html = Str::remove($this->filter($selector)->outerHtml(), $html);
            } else {
                $html = Str::remove($this->filter($selector)->$position()->outerHtml(), $html);
            }

        }

        return $html;
    }
}