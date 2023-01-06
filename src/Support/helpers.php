<?php

/*
 * This file is part of the jiannei/laravel-crawler.
 *
 * (c) jiannei <longjian.huang@foxmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use Illuminate\Support\Facades\Log;

if (!function_exists('mb_internal_encoding')) {
    function mb_internal_encoding($enc)
    {
        return true;
    }
}

/*
 *  mb_regex_encoding()
 *
 *  Included for mbstring pseudo-compatability.
 */
if (!function_exists('mb_regex_encoding')) {
    function mb_regex_encoding($enc)
    {
        return true;
    }
}

/*
 *  mb_strlen()
 *
 *  Included for mbstring pseudo-compatability.
 */
if (!function_exists('mb_strlen')) {
    function mb_strlen($str)
    {
        return strlen($str);
    }
}

/*
 *  mb_strpos()
 *
 *  Included for mbstring pseudo-compatability.
 */
if (!function_exists('mb_strpos')) {
    function mb_strpos($haystack, $needle, $offset = 0)
    {
        return strpos($haystack, $needle, $offset);
    }
}
/*
 *  mb_stripos()
 *
 *  Included for mbstring pseudo-compatability.
 */
if (!function_exists('mb_stripos')) {
    function mb_stripos($haystack, $needle, $offset = 0)
    {
        return stripos($haystack, $needle, $offset);
    }
}

/*
 *  mb_substr()
 *
 *  Included for mbstring pseudo-compatability.
 */
if (!function_exists('mb_substr')) {
    function mb_substr($str, $start, $length = 0)
    {
        return substr($str, $start, $length);
    }
}

/*
 *  mb_substr_count()
 *
 *  Included for mbstring pseudo-compatability.
 */
if (!function_exists('mb_substr_count')) {
    function mb_substr_count($haystack, $needle)
    {
        return substr_count($haystack, $needle);
    }
}

/*
 * Shortcut to phpQuery::pq($arg1, $context)
 * Chainable.
 *
 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
 * @see phpQuery::pq()
 * @author Tobiasz Cudnik <tobiasz.cudnik/gmail.com>
 * @package phpQuery
 */

if (!function_exists('pq')) {
    function pq($arg1, $context = null)
    {
        $args = func_get_args();

        return call_user_func_array(
            ['phpQuery', 'pq'],
            $args
        );
    }
}

if (!function_exists('debug')) {
    function debug($text): void
    {
        if (config('crawler.debug', false)) {
            Log::debug($text);
        }
    };
}