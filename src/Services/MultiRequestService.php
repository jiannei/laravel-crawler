<?php

/*
 * This file is part of the jiannei/laravel-crawler.
 *
 * (c) jiannei <longjian.huang@foxmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Jiannei\LaravelCrawler\Services;

use Closure;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Psr7\Response;
use Jiannei\LaravelCrawler\QueryList;
use Jiannei\LaravelCrawler\Support\Http\GHttp;

/**
 * Class MultiRequestService.
 *
 * @method MultiRequestService withHeaders($headers)
 * @method MultiRequestService withOptions($options)
 * @method MultiRequestService concurrency($concurrency)
 */
class MultiRequestService
{
    protected $ql;
    protected $multiRequest;
    protected $method;

    public function __construct(QueryList $ql, $method, $urls)
    {
        $this->ql = $ql;
        $this->method = $method;
        $this->multiRequest = GHttp::multiRequest($urls);
    }

    public function __call($name, $arguments)
    {
        $this->multiRequest = $this->multiRequest->$name(...$arguments);

        return $this;
    }

    public function success(Closure $success)
    {
        $this->multiRequest = $this->multiRequest->success(function (Response $response, $index) use ($success) {
            $this->ql->setHtml((string) $response->getBody());
            $success($this->ql, $response, $index);
        });

        return $this;
    }

    public function error(Closure $error)
    {
        $this->multiRequest = $this->multiRequest->error(function (TransferException $reason, $index) use ($error) {
            $error($this->ql, $reason, $index);
        });

        return $this;
    }

    public function send()
    {
        $this->multiRequest->{$this->method}();
    }
}
