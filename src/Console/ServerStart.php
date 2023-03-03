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
use Illuminate\Support\Facades\Process;

class ServerStart extends Command
{
    protected $signature = 'crawler:server:start {--path=}';

    protected $description = 'Start a remote end (server)';

    public function handle(): void
    {
        $this->info('Server starting...');

        $driverPath = $this->option('path') ?? 'chromedriver';

        $startCommand = $driverPath.' --port='.config('crawler.chrome.port', 4444);

        $result = Process::run($startCommand);

        if ($result->failed()) {
            $this->error($result->errorOutput());
        } else {
            $this->info('Server started!');
        }
    }
}
