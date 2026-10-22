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
use Jiannei\LaravelCrawler\Exceptions\InvalidPattern;
use Jiannei\LaravelCrawler\Facades\Crawler;
use Jiannei\LaravelCrawler\Models\CrawlTask;
use Jiannei\LaravelCrawler\Sources\FilePatternSource;
use Jiannei\LaravelCrawler\Sources\PatternSourceManager;
use Jiannei\LaravelCrawler\Tests\TestCase;

class PatternSourceTest extends TestCase
{
    public function testFileSourceReturnsPatternsKeyedByKey(): void
    {
        $source = new FilePatternSource($this->tempFile([
            ['key' => 'a', 'url' => 'https://example.test/a', 'rules' => ['title' => ['h1', 'text']]],
            ['key' => 'b', 'url' => 'https://example.test/b', 'rules' => ['title' => ['h1', 'text']]],
        ]));

        $patterns = $source->all();

        $this->assertSame(['a', 'b'], $patterns->keys()->all());
        $this->assertSame('https://example.test/a', $patterns->get('a')['url']);
    }

    public function testFileSourceReplacesItsContents(): void
    {
        $source = new FilePatternSource($this->tempFile([
            ['key' => 'a', 'url' => 'https://example.test/a', 'rules' => ['title' => ['h1', 'text']]],
        ]));

        $source->save([
            ['key' => 'c', 'url' => 'https://example.test/c', 'rules' => ['title' => ['h1', 'text']]],
        ]);

        $this->assertSame(['c'], $source->all()->keys()->all());
    }

    public function testStoredPatternsRequireAKey(): void
    {
        $source = new FilePatternSource($this->tempFile([
            ['url' => 'https://example.test/a', 'rules' => ['title' => ['h1', 'text']]],
        ]));

        $this->expectException(InvalidPattern::class);

        $source->all();
    }

    public function testDatabaseSourceReturnsActivePatterns(): void
    {
        $this->task('a');
        $this->task('b', active: false);

        $patterns = $this->app->make(PatternSourceManager::class)->driver('database')->all();

        $this->assertSame(['a'], $patterns->keys()->all());
        $this->assertSame('a', $patterns->get('a')['key']);
    }

    public function testDatabaseSourceUpsertsAndDeactivatesMissingPatterns(): void
    {
        $this->task('a');
        $this->task('b');

        $source = $this->app->make(PatternSourceManager::class)->driver('database');

        $source->save([
            $this->pattern('a'),
            $this->pattern('c'),
        ]);

        $this->assertTrue(CrawlTask::where('name', 'a')->first()->active);
        $this->assertFalse(CrawlTask::where('name', 'b')->first()->active);
        $this->assertTrue(CrawlTask::where('name', 'c')->first()->active);
        $this->assertSame(['a', 'c'], $source->all()->keys()->all());
    }

    public function testJsonReadsTheConfiguredDatabaseChannel(): void
    {
        $this->app['config']->set('crawler.source.default', 'database');
        $this->task('db:single');

        $this->fakeFetcher($this->response());

        $items = Crawler::json('db:single');

        $this->assertSame('A single page', $items->first()['title']);
    }

    private function task(string $key, bool $active = true): CrawlTask
    {
        return CrawlTask::create([
            'name' => $key,
            'expression' => '* * * * *',
            'active' => $active,
            'pattern' => $this->pattern($key),
        ]);
    }

    private function pattern(string $key): array
    {
        return [
            'key' => $key,
            'url' => 'https://example.test/single',
            'rules' => ['title' => ['#title', 'text']],
        ];
    }

    private function tempFile(array $patterns): string
    {
        $path = tempnam(sys_get_temp_dir(), 'crawler-');
        File::put($path, json_encode($patterns));

        return $path;
    }
}
