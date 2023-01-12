<?php

namespace Jiannei\LaravelCrawler\Listeners;
use GuzzleHttp\Psr7\Message;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Support\Facades\Log;

class RequestSendingListener
{
    public function handle(RequestSending $event)
    {
        $request = Message::toString($event->request->toPsrRequest());

        Log::channel('crawler')->info('http',['hook' => 'RequestSending','request' => $request]);
    }
}