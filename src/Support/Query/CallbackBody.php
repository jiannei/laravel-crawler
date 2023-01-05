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

/**
 * Shorthand for new Callback(create_function(...), ...);.
 *
 * @author Tobiasz Cudnik <tobiasz.cudnik/gmail.com>
 */
class CallbackBody extends Callback
{
    public function __construct(
        $paramList,
        $code,
        $param1 = null,
        $param2 = null,
        $param3 = null
    ) {
        $params = func_get_args();
        $params = array_slice($params, 2);
        $this->callback = create_function($paramList, $code);
        $this->params = $params;
    }
}
