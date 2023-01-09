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

use Closure;
use Illuminate\Support\Collection;
use Jiannei\LaravelCrawler\Services\EncodeService;
use Jiannei\LaravelCrawler\Services\HttpService;
use Jiannei\LaravelCrawler\Services\MultiRequestService;
use Jiannei\LaravelCrawler\Services\PluginService;
use Jiannei\LaravelCrawler\Support\Dom\Elements;
use Jiannei\LaravelCrawler\Support\Query\Dom;
use Jiannei\LaravelCrawler\Support\Query\Parser;

class QueryList
{
    protected $kernel;
    protected static $instance = null;

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

    /**
     * QueryList constructor.
     */
    public function __construct()
    {
//        $this->kernel = (new Kernel($this))->bootstrap();
//        Config::getInstance()->bootstrap($this);
    }

    public function __call($name, $arguments)
    {
        if (!method_exists($this, $name)) {
            throw new \Exception('method not found');
        }

        return $this->$name(...$arguments);
    }

    public static function __callStatic($name, $arguments)
    {
        $instance = new self();

        return $instance->$name(...$arguments);
    }

    public function __destruct()
    {
        // $this->destroyDocument();
    }

    /**
     * Get the QueryList single instance.
     *
     * @return QueryList
     */
    public static function getInstance()
    {
        self::$instance || self::$instance = new self();

        return self::$instance;
    }

    // ========================

    protected function encoding(string $outputEncoding, string $inputEncoding = null)
    {
        return EncodeService::convert($this, $outputEncoding, $inputEncoding);
    }

    protected function pipe(Closure $callback = null)
    {
        return $callback($this);
    }

    protected function use($plugins,...$opt)
    {
        return PluginService::install($this, $plugins, ...$opt);
    }

    protected function get(...$args)
    {
        return HttpService::get($this, ...$args);
    }

    protected function post(...$args)
    {
        return HttpService::get($this, ...$args);
    }

    protected function postJson(...$args)
    {
        return HttpService::postJson($this, ...$args);
    }

    protected function multiGet(...$args)
    {
        return new MultiRequestService($this, 'get', ...$args);
    }

    protected function multiPost(...$args)
    {
        return new MultiRequestService($this, 'post', ...$args);
    }

    protected function queryData(Closure $callback = null)
    {
        return $this->query()->getData($callback)->all();
    }

    protected function html($html)
    {
        return $this->setHtml($html);
    }

    /**
     * @param $html
     * @param null $charset
     *
     * @return QueryList
     */
    protected function setHtml($html)
    {
        $this->html = value($html);
        $this->destroyDocument();
        $this->parser = Dom::newDocument($this->html);

        return $this;
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
    protected function rules(array $rules)
    {
        $this->rules = $rules;

        return $this;
    }

    /**
     * Set the slice area for crawl list.
     *
     * @param $selector
     *
     * @return QueryList
     */
    protected function range($selector)
    {
        $this->range = $selector;

        return $this;
    }

    /**
     * Remove HTML head,try to solve the garbled.
     *
     * @return QueryList
     */
    protected function removeHead()
    {
        $html = preg_replace('/(<head>|<head\s+.+?>).+<\/head>/is', '<head></head>', $this->html);
        $this->setHtml($html);

        return $this;
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

        return $this;
    }

    protected function handleData(Collection $data, $callback)
    {
        if (is_callable($callback)) {
            if (empty($this->range)) {
                $data = collect($callback($data->all(), null));
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
                    $contentElements = Dom::parse($element)->find($rule['selector']);
                    $data[$i][$key] = $this->extractContent($contentElements, $key, $rule);
                }
                ++$i;
            }
        }

        return collect($data);
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
            $doc = Dom::newDocument($html);

            $doc->find($tag_str)->remove();

            $html =$doc->htmlOuter();

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
}
