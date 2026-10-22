<?php

/*
 * This file is part of the jiannei/laravel-crawler.
 *
 * (c) jiannei <jiannei@sinan.fun>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Jiannei\LaravelCrawler\Sources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Jiannei\LaravelCrawler\Contracts\PatternSource;
use Jiannei\LaravelCrawler\Exceptions\InvalidPattern;

class DatabasePatternSource implements PatternSource
{
    public function __construct(private readonly string $model)
    {
    }

    public function all(): Collection
    {
        $model = $this->model();

        return $model::query()
            ->where('active', true)
            ->orderBy('id')
            ->get()
            ->mapWithKeys(function (Model $task) {
                $pattern = (array) $task->pattern;
                $pattern['key'] = $task->name;

                return [$task->name => $pattern];
            });
    }

    public function save(array $patterns): void
    {
        $keyed = Patterns::keyed($patterns);
        $model = $this->model();

        DB::transaction(function () use ($keyed, $model) {
            foreach ($keyed as $key => $pattern) {
                $model::updateOrCreate(['name' => $key], [
                    'expression' => (string) ($pattern['expression'] ?? '* * * * *'),
                    'pattern' => $pattern,
                    'active' => true,
                ]);
            }

            $model::query()
                ->whereNotIn('name', $keyed->keys()->all())
                ->update(['active' => false]);
        });
    }

    protected function model(): string
    {
        if (!class_exists($this->model) || !is_subclass_of($this->model, Model::class)) {
            throw new InvalidPattern("The database source model [{$this->model}] is invalid.");
        }

        return $this->model;
    }
}
