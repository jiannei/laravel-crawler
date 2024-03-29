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

use Illuminate\Database\Eloquent\Model;

class CrawlRecord extends Model
{
    protected $fillable = [
        'content',
    ];
    protected $casts = [
        'content' => 'json',
    ];

    public function task()
    {
        return $this->belongsTo(CrawlTask::class, 'task_id');
    }
}
