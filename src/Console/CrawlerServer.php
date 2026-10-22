<?php

/*
 * This file is part of the jiannei/laravel-crawler.
 *
 * (c) jiannei <jiannei@sinan.fun>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Jiannei\LaravelCrawler\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Illuminate\Support\Facades\Process;
use Jiannei\LaravelCrawler\Chrome\ChromeSettings;

class CrawlerServer extends Command implements Isolatable
{
    protected $signature = 'crawler:server {--path=}';

    protected $description = 'Start a remote end (server)';

    public function handle(ChromeSettings $settings)
    {
        if ($pid = Process::run('pgrep chromedriver')->output()) {
            Process::run('kill -9 '.$pid);
        }

        $binary = $settings->binary;

        if ($path = $this->option('path')) {
            $binary = rtrim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$binary;
        }

        $startCommand = escapeshellarg($binary)
            .' --port='.$settings->port
            .' --log-path='.escapeshellarg($settings->logPath)
            .' --log-level='.escapeshellarg($settings->logLevel);

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
