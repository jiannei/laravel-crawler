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

use Jiannei\LaravelCrawler\Contracts\ServiceProviderContract;
use Jiannei\LaravelCrawler\Exceptions\ServiceNotFoundException;
use Jiannei\LaravelCrawler\Providers\EncodeServiceProvider;
use Jiannei\LaravelCrawler\Providers\HttpServiceProvider;
use Jiannei\LaravelCrawler\Providers\PluginServiceProvider;
use Jiannei\LaravelCrawler\Providers\SystemServiceProvider;
use Closure;
use Illuminate\Support\Collection;

class Kernel
{
    protected array $providers = [
        SystemServiceProvider::class,
        HttpServiceProvider::class,
        EncodeServiceProvider::class,
        PluginServiceProvider::class,
    ];

    protected $binds;
    protected $ql;

    /**
     * Kernel constructor.
     *
     * @param $ql
     */
    public function __construct(QueryList $ql)
    {
        $this->ql = $ql;
        $this->binds = new Collection();
    }

    public function bootstrap()
    {
        // 注册服务提供者
        $this->registerProviders();

        return $this;
    }

    public function registerProviders()
    {
        foreach ($this->providers as $provider) {
            $this->register(new $provider());
        }
    }

    public function bind(string $name, Closure $provider)
    {
        $this->binds[$name] = $provider;
    }

    public function getService(string $name)
    {
        if (!$this->binds->offsetExists($name)) {
            throw new ServiceNotFoundException("Service: {$name} not found!");
        }

        return $this->binds[$name];
    }

    private function register(ServiceProviderContract $instance)
    {
        $instance->register($this);
    }
}
