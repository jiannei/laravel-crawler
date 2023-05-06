<?php

/*
 * This file is part of the jiannei/laravel-crawler.
 *
 * (c) jiannei <longjian.huang@foxmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Jiannei\LaravelCrawler\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class CrawlerServer extends Command implements Isolatable
{
    protected $signature = 'crawler:server {--path=}';

    protected $description = 'Start a remote end (server)';

    public function handle()
    {
        if ($pid = Process::run('pgrep chromedriver')->output()) {
            Process::run('kill -9 '.$pid);
        }

        $driverPath = $this->option('path') ?? '';
        if ($driverPath) {
            $driverPath = Str::of($driverPath)->finish(DIRECTORY_SEPARATOR);
        }

        $startCommand = $driverPath
            .'chromedriver --port='.config('crawler.chrome.port', 4444)
            .' --log-path='.config('crawler.chrome.log.path')
            .' --log-level='.config('crawler.chrome.log.level');

        $process = Process::start($startCommand);

        while ($process->running()) {
            $output = $process->latestOutput();

            if ($output) {
                $this->info($output);
            }

            $output = '';
            sleep(1);
        }

        $result = $process->wait();
        if ($result->failed()) {
            $this->error($result->errorOutput());
        }
    }
}
