<?php

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
     * Attempts to detect the encoding
     * @param $string
     * @return bool|false|mixed|string
     */
    public static function detect($string)
    {
        $charset = mb_detect_encoding($string, array('ASCII', 'GB2312', 'GBK', 'UTF-8'), true);
        if (strtolower($charset) == 'cp936') {
            $charset = 'GBK';
        }

        return $charset;
    }

}