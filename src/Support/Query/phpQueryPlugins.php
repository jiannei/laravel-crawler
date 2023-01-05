<?php

/*
 * This file is part of the jiannei/laravel-crawler.
 *
 * (c) jiannei <longjian.huang@foxmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Jiannei\LaravelCrawler\Support\Query;

use Jiannei\LaravelCrawler\Query\Exception;

/**
 * Plugins static namespace class.
 *
 * @author Tobiasz Cudnik <tobiasz.cudnik/gmail.com>
 *
 * @todo move plugin methods here (as statics)
 */
class phpQueryPlugins
{
    public function __call($method, $args)
    {
        if (isset(phpQuery::$extendStaticMethods[$method])) {
            $return = call_user_func_array(
                phpQuery::$extendStaticMethods[$method],
                $args
            );
        } else {
            if (isset(phpQuery::$pluginsStaticMethods[$method])) {
                $class = phpQuery::$pluginsStaticMethods[$method];
                $realClass = "phpQueryPlugin_$class";
                $return = call_user_func_array(
                    [$realClass, $method],
                    $args
                );

                return isset($return)
                    ? $return
                    : $this;
            } else {
                throw new Exception("Method '{$method}' doesnt exist");
            }
        }
    }
}
