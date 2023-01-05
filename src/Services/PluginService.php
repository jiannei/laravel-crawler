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

use Jiannei\LaravelCrawler\QueryList;

class PluginService
{
    public static function install(QueryList $queryList, $plugins, ...$opt)
    {
        if (is_array($plugins)) {
            foreach ($plugins as $plugin) {
                $plugin::install($queryList);
            }
        } else {
            $plugins::install($queryList, ...$opt);
        }

        return $queryList;
    }
}
