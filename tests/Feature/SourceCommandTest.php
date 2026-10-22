<?php

/*
 * This file is part of the jiannei/laravel-crawler.
 *
 * (c) jiannei <jiannei@sinan.fun>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Jiannei\LaravelCrawler\Tests\Feature;

use Illuminate\Support\Facades\File;
use Jiannei\LaravelCrawler\Models\CrawlTask;
use Jiannei\LaravelCrawler\Tests\TestCase;

class SourceCommandTest extends TestCase
{
    public function testImportMirrorsTheFileIntoTheDatabase(): void
    {
        $path = $this->tempFile([
            [
                'key' => 'imported',
                'url' => 'https://example.test/single',
                'rules' => ['title' => ['#title', 'text']],
            ],
        ]);

        $this->app['config']->set('crawler.source.channels.file.path', $path);

        $this->artisan('crawler:task', ['--action' => 'import'])
            ->assertExitCode(0);

        $this->assertDatabaseHas('crawl_tasks', [
            'name' => 'imported',
            'active' => true,
        ]);
    }

    public function testExportMirrorsTheDatabaseIntoTheFile(): void
    {
        $path = $this->tempFile([]);

        $this->app['config']->set('crawler.source.channels.file.path', $path);

        CrawlTask::create([
            'name' => 'exported',
            'expression' => '* * * * *',
            'pattern' => [
                'key' => 'exported',
                'url' => 'https://example.test/single',
                'rules' => ['title' => ['#title', 'text']],
            ],
        ]);

        $this->artisan('crawler:task', ['--action' => 'export'])
            ->assertExitCode(0);

        $patterns = json_decode(File::get($path), true);

        $this->assertSame(['exported'], array_column($patterns, 'key'));
    }

    private function tempFile(array $patterns): string
    {
        $path = tempnam(sys_get_temp_dir(), 'crawler-');
        File::put($path, json_encode($patterns));

        return $path;
    }
}
