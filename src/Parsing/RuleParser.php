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
use Illuminate\Support\Str;
use Jiannei\LaravelCrawler\Exceptions\InvalidPattern;
use Symfony\Component\DomCrawler\Crawler as SymfonyCrawler;

/**
 * Turns a Pattern's field rules into one item.
 */
final class RuleParser
{
    public static function validateRules(array $rules): void
    {
        foreach ($rules as $field => $rule) {
            if (is_string($rule)) {
                continue;
            }

            if (!is_array($rule) || count($rule) < 2) {
                throw new InvalidPattern("The [$field] rule is invalid.");
            }

            [$selector, $attribute] = $rule;

            if (!is_string($selector) || '' === $selector || !is_string($attribute) || '' === $attribute) {
                throw new InvalidPattern("The [$field] rule is invalid.");
            }
        }
    }

    public static function parseNode(SymfonyCrawler $node, array $rules): array
    {
        $item = [];

        foreach ($rules as $field => $rule) {
            if (is_string($rule)) {
                Arr::set($item, $field, $rule);
                continue;
            }

            if (!is_array($rule) || count($rule) < 2) {
                throw new InvalidPattern("The [$field] rule is invalid.");
            }

            [$selector, $attribute, $position, $closure] = array_pad($rule, 4, null);

            if (!is_string($selector) || '' === $selector || !is_string($attribute) || '' === $attribute) {
                throw new InvalidPattern("The [$field] rule is invalid.");
            }

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

            if (in_array($attribute, ['text', 'html', 'outerHtml'], true)) {
                $result = $element->{$attribute}();
            } else {
                $result = $element->attr($attribute);
            }

            $result = is_callable($closure) ? $closure($node, Str::of((string) $result)) : $result;

            Arr::set($item, $field, $result);
        }

        return $item;
    }

    public static function parseMany(SymfonyCrawler $crawler, array $rules): array
    {
        return $crawler->each(function (SymfonyCrawler $node) use ($rules) {
            return self::parseNode($node, $rules);
        });
    }
}
