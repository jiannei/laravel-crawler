<?php

/*
 * This file is part of the jiannei/laravel-crawler.
 *
 * (c) jiannei <longjian.huang@foxmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Jiannei\LaravelCrawler\Listeners;

use GuzzleHttp\Psr7\Message;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Support\Facades\Log;

class ConnectionFailedListener
{
    public function handle(ConnectionFailed $event)
    {
        $request = Message::toString($event->request->toPsrRequest());

        Log::channel('crawler')->info('http', ['hook' => 'ConnectionFailed', 'request' => $request]);
    }
}
