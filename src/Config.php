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

class Config
{
    protected static $instance = null;

    protected $plugins;
    protected $binds;

    /**
     * Config constructor.
     */
    public function __construct()
    {
        $this->plugins = new Collection();
        $this->binds = new Collection();
    }

    /**
     * Get the Config instance.
     *
     * @return Config|null
     */
    public static function getInstance()
    {
        self::$instance || self::$instance = new self();

        return self::$instance;
    }

    /**
     * Global installation plugin.
     *
     * @param $plugins
     * @param array ...$opt
     *
     * @return $this
     */
    public function use($plugins, ...$opt)
    {
        if (is_string($plugins)) {
            $this->plugins->push([$plugins, $opt]);
        } else {
            $this->plugins = $this->plugins->merge($plugins);
        }

        return $this;
    }

    /**
     * Global binding custom method.
     *
     * @return $this
     */
    public function bind(string $name, Closure $provider)
    {
        $this->binds[$name] = $provider;

        return $this;
    }

    public function bootstrap(QueryList $queryList)
    {
        $this->installPlugins($queryList);
        $this->installBind($queryList);
    }

    protected function installPlugins(QueryList $queryList)
    {
        $this->plugins->each(function ($plugin) use ($queryList) {
            if (is_string($plugin)) {
                $queryList->use($plugin);
            } else {
                $queryList->use($plugin[0], ...$plugin[1]);
            }
        });
    }

    protected function installBind(QueryList $queryList)
    {
        $this->binds->each(function ($provider, $name) use ($queryList) {
            $queryList->bind($name, $provider);
        });
    }
}
