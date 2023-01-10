<?php

namespace Jiannei\LaravelCrawler;

use Illuminate\Support\Facades\Http;
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

}