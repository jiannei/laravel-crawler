<?php

namespace Jiannei\LaravelCrawler\Listeners;
use GuzzleHttp\Psr7\Message;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Support\Facades\Log;

class ResponseReceivedListener
{
    public function handle(ResponseReceived $event)
    {
        $request = Message::toString($event->request->toPsrRequest());
        $response = Message::toString($event->response->toPsrResponse());

        Log::channel('crawler')->info('http',['hook' => 'ResponseReceived','request' => $request,'response' => $response]);
    }
}