<?php

/*
 * This file is part of the jiannei/laravel-crawler.
 *
 * (c) jiannei <longjian.huang@foxmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

return [
    'http' => [
        // https://docs.guzzlephp.org/en/stable/request-options.html
        'options' => [
            'debug' => false,

            'headers' => [
                'Accept-Encoding' => 'gzip',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36',
            ],
        ],
    ],
];
