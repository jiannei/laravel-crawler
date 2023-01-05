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

class EncodeService
{
    public static function convert(QueryList $ql, string $outputEncoding, string $inputEncoding = null)
    {
        $html = $ql->getHtml();
        $inputEncoding || $inputEncoding = self::detect($html);
        $html = iconv($inputEncoding, $outputEncoding.'//IGNORE', $html);
        $ql->setHtml($html);

        return $ql;
    }

    /**
     * Attempts to detect the encoding.
     *
     * @param $string
     *
     * @return bool|false|mixed|string
     */
    public static function detect($string)
    {
        $charset = mb_detect_encoding($string, ['ASCII', 'GB2312', 'GBK', 'UTF-8'], true);
        if ('cp936' == strtolower($charset)) {
            $charset = 'GBK';
        }

        return $charset;
    }
}
