<?php

namespace Jiannei\LaravelCrawler;

use Illuminate\Support\Arr;
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

    public function attrs(string|array $attribute):array
    {
        return $this->extract(Arr::wrap($attribute));
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

    public function rules(array $rules):array
    {
        return $this->each(function (SymfonyCrawler $node) use ($rules) {
            $item = [];
            foreach ($rules as $field => $rule) {
                if (!is_array($rule) || count($rule) !== 2 ) {
                    throw new \InvalidArgumentException("The [$field] rule is invalid.");
                }

                $selectors = explode(':',$rule[0]);
                $method = $rule[1];

                $position = '';
                $selector = $selectors[0];
                if (count($selectors) > 1 && in_array($selectors[1],['first', 'last'])) {
                    $position = $selectors[1];
                }

                if (!$node->filter($selector)->count()) {
                    continue;
                }

                if (!$position) {
                    $item[$field] = !in_array($method, ['text', 'html','outerHtml']) ? $node->filter($selector)->attr($method) : $node->filter($selector)->$method();
                }else{
                    $item[$field] = !in_array($method, ['text', 'html','outerHtml']) ? $node->filter($selector)->$position()->attr($method) : $node->filter($selector)->$position()->$method();
                }
            }

            return $item;
        });
    }

    public function remove(array $elements): string
    {
        $rules = [];
        foreach ($elements as $element) {
            $rules[] = [$element,'outerHtml'];
        }

        $html = $this->html();
        foreach ($this->rules($rules) as $items) {
            $html = Str::remove($items, $html);
        }

        return $html;
    }
}