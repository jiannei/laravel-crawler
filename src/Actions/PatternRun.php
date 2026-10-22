<?php

/*
 * This file is part of the jiannei/laravel-crawler.
 *
 * (c) jiannei <jiannei@sinan.fun>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Jiannei\LaravelCrawler\Actions;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Jiannei\LaravelCrawler\Contracts\Fetcher;
use Jiannei\LaravelCrawler\Data\FetchResponse;
use Jiannei\LaravelCrawler\Data\RunOptions;
use Jiannei\LaravelCrawler\Exceptions\FetchFailed;
use Jiannei\LaravelCrawler\Exceptions\InvalidPattern;
use Jiannei\LaravelCrawler\Parsing\Page;
use Jiannei\LaravelCrawler\Parsing\RuleParser;

/**
 * Executes one Pattern: fetch, apply hooks, parse, and shape items.
 */
final class PatternRun
{
    public function __construct(private readonly Fetcher $fetcher)
    {
    }

    public function run(array $pattern, RunOptions $options = new RunOptions()): Collection
    {
        $this->validate($pattern);

        [$url, $query, $httpOptions] = $this->request($pattern, $options);

        $response = $this->fetcher->fetch($url, $query, $httpOptions);

        if ($options->after) {
            $response = ($options->after)($response);

            if (!$response instanceof FetchResponse) {
                throw new InvalidPattern('The after hook must return a FetchResponse.');
            }
        }

        if (!$response->successful()) {
            throw new FetchFailed($url, $response->status, $response->body);
        }

        return $this->shape($pattern, new Page($response->body));
    }

    /**
     * Reject an invalid pattern before any request is sent.
     */
    private function validate(array $pattern): void
    {
        $url = $pattern['url'] ?? null;
        if (!is_string($url) || '' === $url) {
            throw new InvalidPattern('The [url] pattern is required.');
        }

        $group = $pattern['group'] ?? [];

        if (!$group) {
            $rules = $pattern['rules'] ?? null;
            if (!is_array($rules) || [] === $rules) {
                throw new InvalidPattern('The [rules] pattern is required.');
            }

            RuleParser::validateRules($rules);

            return;
        }

        if (!empty($pattern['rss']) && !Arr::isList($group)) {
            throw new InvalidPattern('RSS patterns require a list of groups.');
        }

        foreach (Arr::isList($group) ? $group : [$group] as $item) {
            if (!is_array($item)) {
                throw new InvalidPattern('The [group] pattern is invalid.');
            }

            RuleParser::validateRules($this->rules($item));
            $this->selector($item);
        }
    }

    /**
     * Resolve the request, applying the pattern and the per-call options.
     *
     * @return array{0: string, 1: array|string|null, 2: array}
     */
    private function request(array $pattern, RunOptions $options): array
    {
        $url = $pattern['url'];

        $query = array_merge((array) ($pattern['query'] ?? []), $options->query);
        $httpOptions = array_merge((array) ($pattern['options'] ?? []), $options->options);

        if ($options->before) {
            $request = ($options->before)($url, $query, $httpOptions);

            if (!is_array($request) || count($request) < 3) {
                throw new InvalidPattern('The before hook must return [url, query, options].');
            }

            [$url, $query, $httpOptions] = $request;
        }

        return [$url, $query, $httpOptions];
    }

    /**
     * Shape the parsed page according to the pattern's groups.
     */
    private function shape(array $pattern, Page $page): Collection
    {
        $group = $pattern['group'] ?? [];

        if ($group) {
            if (Arr::isList($group)) {
                $items = collect();

                foreach ($group as $key => $item) {
                    $items->put(
                        $item['alias'] ?? $key,
                        collect(RuleParser::parseMany($page->filter($this->selector($item)), $this->rules($item)))
                    );
                }
            } else {
                $items = collect(RuleParser::parseMany(
                    $page->filter($this->selector($group)),
                    $this->rules($group)
                ));
            }
        } else {
            $rules = $pattern['rules'] ?? null;
            if (!is_array($rules) || [] === $rules) {
                throw new InvalidPattern('The [rules] pattern is required.');
            }

            $items = collect([RuleParser::parseNode($page, $rules)]);
        }

        if (empty($pattern['rss'])) {
            return $items;
        }

        if (empty($group) || !Arr::isList($group)) {
            throw new InvalidPattern('RSS patterns require a list of groups.');
        }

        return $items->map(function (Collection $groupItems, string|int $alias) {
            return 'channel' === $alias ? $groupItems->first() : $groupItems->all();
        });
    }

    private function selector(array $group): string
    {
        $selector = $group['selector'] ?? null;
        if (!is_string($selector) || '' === $selector) {
            throw new InvalidPattern('The [selector] group key is required.');
        }

        return $selector;
    }

    private function rules(array $group): array
    {
        $rules = $group['rules'] ?? null;
        if (!is_array($rules) || [] === $rules) {
            throw new InvalidPattern('The [rules] group key is required.');
        }

        return $rules;
    }
}
