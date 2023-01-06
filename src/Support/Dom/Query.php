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

use Closure;
use Illuminate\Support\Collection;
use Jiannei\LaravelCrawler\QueryList;
use Jiannei\LaravelCrawler\Support\Query\phpQuery;
use Jiannei\LaravelCrawler\Support\Query\Parser;

class Query
{
    protected $html;
    /**
     * @var Parser
     */
    protected $parser;
    protected $rules;
    protected $range = null;
    protected $ql;
    /**
     * @var Collection
     */
    protected $data;

    public function __construct(QueryList $ql)
    {
        $this->ql = $ql;
    }

    /**
     * @param bool $rel
     *
     * @return string
     */
    public function getHtml($rel = true)
    {
        return $rel ? $this->parser->htmlOuter() : $this->html;
    }

    /**
     * @param $html
     * @param null $charset
     *
     * @return QueryList
     */
    public function setHtml($html)
    {
        $this->html = value($html);
        $this->destroyDocument();
        $this->parser = phpQuery::newDocument($this->html);

        return $this->ql;
    }

    /**
     * Get crawl results.
     *
     * @return Collection|static
     */
    public function getData(Closure $callback = null)
    {
        return $this->handleData($this->data, $callback);
    }

    public function setData(Collection $data)
    {
        $this->data = $data;
    }

    /**
     * Searches for all elements that match the specified expression.
     *
     * @param string $selector a string containing a selector expression to match elements against
     *
     * @return Elements
     */
    public function find($selector)
    {
        $elements = $this->parser->find($selector);

        return new Elements($elements);
    }

    /**
     * Set crawl rule.
     *
     * $rules = [
     *    'rule_name1' => ['selector','HTML attribute | text | html','Tag filter list','callback'],
     *    'rule_name2' => ['selector','HTML attribute | text | html','Tag filter list','callback'],
     *    // ...
     *  ]
     *
     * @return QueryList
     */
    public function rules(array $rules)
    {
        $this->rules = $rules;

        return $this->ql;
    }

    /**
     * Set the slice area for crawl list.
     *
     * @param $selector
     *
     * @return QueryList
     */
    public function range($selector)
    {
        $this->range = $selector;

        return $this->ql;
    }

    /**
     * Remove HTML head,try to solve the garbled.
     *
     * @return QueryList
     */
    public function removeHead()
    {
        $html = preg_replace('/(<head>|<head\s+.+?>).+<\/head>/is', '<head></head>', $this->html);
        $this->setHtml($html);

        return $this->ql;
    }

    /**
     * Execute the query rule.
     *
     * @return QueryList
     */
    public function query(Closure $callback = null)
    {
        $this->data = $this->getList();
        $this->data = $this->handleData($this->data, $callback);

        return $this->ql;
    }

    protected function handleData(Collection $data, $callback)
    {
        if (is_callable($callback)) {
            if (empty($this->range)) {
                $data = new Collection($callback($data->all(), null));
            } else {
                $data = $data->map($callback);
            }
        }

        return $data;
    }

    protected function getList()
    {
        $data = [];
        if (empty($this->range)) {
            foreach ($this->rules as $key => $reg_value) {
                $rule = $this->parseRule($reg_value);
                $contentElements = $this->parser->find($rule['selector']);
                $data[$key] = $this->extractContent($contentElements, $key, $rule);
            }
        } else {
            $rangeElements = $this->parser->find($this->range);
            $i = 0;
            foreach ($rangeElements as $element) {
                foreach ($this->rules as $key => $reg_value) {
                    $rule = $this->parseRule($reg_value);
                    $contentElements = phpQuery::pq($element)->find($rule['selector']);
                    $data[$i][$key] = $this->extractContent($contentElements, $key, $rule);
                }
                ++$i;
            }
        }

        return new Collection($data);
    }

    protected function extractContent(Parser $pqObj, $ruleName, $rule)
    {
        switch ($rule['attr']) {
            case 'text':
                $content = $this->allowTags($pqObj->html(), $rule['filter_tags']);
                break;
            case 'texts':
                $content = (new Elements($pqObj))->map(function (Elements $element) use ($rule) {
                    return $this->allowTags($element->html(), $rule['filter_tags']);
                })->all();
                break;
            case 'html':
                $content = $this->stripTags($pqObj->html(), $rule['filter_tags']);
                break;
            case 'htmls':
                $content = (new Elements($pqObj))->map(function (Elements $element) use ($rule) {
                    return $this->stripTags($element->html(), $rule['filter_tags']);
                })->all();
                break;
            case 'htmlOuter':
                $content = $this->stripTags($pqObj->htmlOuter(), $rule['filter_tags']);
                break;
            case 'htmlOuters':
                $content = (new Elements($pqObj))->map(function (Elements $element) use ($rule) {
                    return $this->stripTags($element->htmlOuter(), $rule['filter_tags']);
                })->all();
                break;
            default:
                if (preg_match('/attr\((.+)\)/', $rule['attr'], $arr)) {
                    $content = $pqObj->attr($arr[1]);
                } elseif (preg_match('/attrs\((.+)\)/', $rule['attr'], $arr)) {
                    $content = (new Elements($pqObj))->attrs($arr[1])->all();
                } else {
                    $content = $pqObj->attr($rule['attr']);
                }
                break;
        }

        if (is_callable($rule['handle_callback'])) {
            $content = call_user_func($rule['handle_callback'], $content, $ruleName);
        }

        return $content;
    }

    protected function parseRule($rule)
    {
        $result = [];
        $result['selector'] = $rule[0];
        $result['attr'] = $rule[1];
        $result['filter_tags'] = $rule[2] ?? '';
        $result['handle_callback'] = $rule[3] ?? null;

        return $result;
    }

    /**
     * 去除特定的html标签.
     *
     * @param string $html
     * @param string $tags_str 多个标签名之间用空格隔开
     *
     * @return string
     */
    protected function stripTags($html, $tags_str)
    {
        $tagsArr = $this->tag($tags_str);
        $html = $this->removeTags($html, $tagsArr[1]);
        $p = [];
        foreach ($tagsArr[0] as $tag) {
            $p[] = "/(<(?:\/".$tag.'|'.$tag.')[^>]*>)/i';
        }

        return preg_replace($p, '', trim($html));
    }

    /**
     * 保留特定的html标签.
     *
     * @param string $html
     * @param string $tags_str 多个标签名之间用空格隔开
     *
     * @return string
     */
    protected function allowTags($html, $tags_str)
    {
        $tagsArr = $this->tag($tags_str);
        $html = $this->removeTags($html, $tagsArr[1]);
        $allow = '';
        foreach ($tagsArr[0] as $tag) {
            $allow .= "<$tag> ";
        }

        return strip_tags(trim($html), $allow);
    }

    protected function tag($tags_str)
    {
        $tagArr = preg_split("/\s+/", $tags_str, -1, PREG_SPLIT_NO_EMPTY);
        $tags = [[], []];
        foreach ($tagArr as $tag) {
            if (preg_match('/-(.+)/', $tag, $arr)) {
                array_push($tags[1], $arr[1]);
            } else {
                array_push($tags[0], $tag);
            }
        }

        return $tags;
    }

    /**
     * 移除特定的html标签.
     *
     * @param string $html
     * @param array  $tags 标签数组
     *
     * @return string
     */
    protected function removeTags($html, $tags)
    {
        $tag_str = '';
        if (count($tags)) {
            foreach ($tags as $tag) {
                $tag_str .= $tag_str ? ','.$tag : $tag;
            }
            $doc = phpQuery::newDocument($html);
            phpQuery::pq($doc)->find($tag_str)->remove();
            $html = phpQuery::pq($doc)->htmlOuter();
            $doc->unloadDocument();
        }

        return $html;
    }

    protected function destroyDocument()
    {
        if ($this->parser instanceof Parser) {
            $this->parser->unloadDocument();
        }
    }

    public function __destruct()
    {
        $this->destroyDocument();
    }
}