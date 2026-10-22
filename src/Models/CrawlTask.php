<?php

/*
 * This file is part of the jiannei/laravel-crawler.
 *
 * (c) jiannei <jiannei@sinan.fun>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Jiannei\LaravelCrawler\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Jiannei\LaravelCrawler\Enums\TaskStatus;

/**
 * @method static \Illuminate\Database\Eloquent\Builder|CrawlTask active()
 */
class CrawlTask extends Model
{
    protected $fillable = [
        'name',
        'expression',
        'pattern',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'pattern' => 'json',
        'status' => TaskStatus::class,
        'previous_run_date' => 'datetime',
        'next_run_date' => 'datetime',
    ];

    public function records()
    {
        return $this->hasMany(CrawlRecord::class, 'task_id');
    }

    /**
     * Scope a query to only include active tasks.
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }
}
