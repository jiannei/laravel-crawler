<?php

namespace Jiannei\LaravelCrawler\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

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
