<?php

/*
 * This file is part of the jiannei/laravel-crawler.
 *
 * (c) jiannei <longjian.huang@foxmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Jiannei\LaravelCrawler\Support\Http;

use Closure;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;

class MultiRequest
{
    protected $client;
    protected $headers = [];
    protected $options = [];
    protected $successCallback;
    protected $errorCallback;
    protected $urls = [];
    protected $method;
    protected $concurrency = 5;

    public function __construct(Client $client)
    {
        $this->client = $client;
        $this->headers = [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36',
        ];
    }

    public static function newRequest(Client $client)
    {
        $request = new self($client);

        return $request;
    }

    public function withHeaders($headers)
    {
        $this->headers = array_merge($this->headers, $headers);

        return $this;
    }

    public function withOptions($options)
    {
        $this->options = $options;

        return $this;
    }

    public function concurrency($concurrency)
    {
        $this->concurrency = $concurrency;

        return $this;
    }

    public function success(Closure $success)
    {
        $this->successCallback = $success;

        return $this;
    }

    public function error(Closure $error)
    {
        $this->errorCallback = $error;

        return $this;
    }

    public function urls(array $urls)
    {
        $this->urls = $urls;

        return $this;
    }

    public function get()
    {
        $this->method = 'GET';
        $this->send();
    }

    public function post()
    {
        $this->method = 'POST';
        $this->send();
    }

    protected function send()
    {
        $client = $this->client;

        $requests = function ($urls) {
            foreach ($urls as $url) {
                if (is_string($url)) {
                    yield new Request($this->method, $url, $this->headers);
                } else {
                    yield $url;
                }
            }
        };

        $pool = new Pool($client, $requests($this->urls), [
            'concurrency' => $this->concurrency,
            'fulfilled' => $this->successCallback,
            'rejected' => $this->errorCallback,
            'options' => $this->options,
        ]);

        $promise = $pool->promise();
        $promise->wait();
    }
}
