<?php

namespace Jiannei\LaravelCrawler\Contracts;

use QL\QueryList;

interface PluginContract
{
    public static function install(QueryList $queryList,...$opt);
}