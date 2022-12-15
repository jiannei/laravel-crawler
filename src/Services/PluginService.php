<?php

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