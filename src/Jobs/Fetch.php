<?php

namespace Jiannei\LaravelCrawler\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Jiannei\LaravelCrawler\Support\Facades\Crawler;

class Fetch implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public string $url, public array|string|null $query = null, public array $options = [])
    {
        $this->onQueue('crawler');
    }

    public function handle()
    {
        $response = Crawler::client($this->options)->get($this->url, $this->query);

        Cache::put('crawler:fetch:'.md5($this->url), $response->body(), config('crawler.fetch.cache'));
    }
}
