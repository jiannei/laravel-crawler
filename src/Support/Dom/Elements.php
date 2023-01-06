<?php

/*
 * This file is part of the jiannei/laravel-crawler.
 *
 * (c) jiannei <longjian.huang@foxmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Jiannei\LaravelCrawler\Support\Dom;

use Illuminate\Support\Collection;
use Jiannei\LaravelCrawler\Support\Query\Dom;
use Jiannei\LaravelCrawler\Support\Query\Parser;

class Elements
{
    /**
     * @var Parser
     */
    protected $elements;

    /**
     * Elements constructor.
     *
     * @param $elements
     */
    public function __construct(Parser $elements)
    {
        $this->elements = $elements;
    }

    public function __get($name)
    {
        return property_exists($this->elements, $name) ? $this->elements->$name : $this->elements->attr($name);
    }

    public function __call($name, $arguments)
    {
        $obj = call_user_func_array([$this->elements, $name], $arguments);
        if ($obj instanceof Parser) {
            $obj = new self($obj);
        } elseif (is_string($obj)) {
            $obj = trim($obj);
        }

        return $obj;
    }

    /**
     * Iterating elements.
     *
     * @return $this
     */
    public function each(callable $callback)
    {
        foreach ($this->elements as $key => $element) {
            $break = $callback(new self(Dom::pq($element)), $key);
            if (false === $break) {
                break;
            }
        }

        return $this;
    }

    /**
     * Iterating elements.
     *
     * @param $callback
     *
     * @return \Illuminate\Support\Collection
     */
    public function map($callback)
    {
        $collection = new Collection();
        $this->elements->each(function ($dom) use (&$collection, $callback) {
            $collection->push($callback(new self(Dom::pq($dom))));
        });

        return $collection;
    }

    /**
     * Gets the attributes of all the elements.
     *
     * @param string $attr HTML attribute name
     *
     * @return \Illuminate\Support\Collection
     */
    public function attrs($attr)
    {
        return $this->map(function ($item) use ($attr) {
            return $item->attr($attr);
        });
    }

    /**
     * Gets the text of all the elements.
     *
     * @return \Illuminate\Support\Collection
     */
    public function texts()
    {
        return $this->map(function ($item) {
            return trim($item->text());
        });
    }

    /**
     * Gets the html of all the elements.
     *
     * @return \Illuminate\Support\Collection
     */
    public function htmls()
    {
        return $this->map(function ($item) {
            return trim($item->html());
        });
    }

    /**
     * Gets the htmlOuter of all the elements.
     *
     * @return \Illuminate\Support\Collection
     */
    public function htmlOuters()
    {
        return $this->map(function ($item) {
            return trim($item->htmlOuter());
        });
    }

    public function getElements(): Parser
    {
        return $this->elements;
    }
}
