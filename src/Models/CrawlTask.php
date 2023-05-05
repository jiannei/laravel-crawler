<?php

/*
 * This file is part of the jiannei/laravel-crawler.
 *
 * (c) jiannei <longjian.huang@foxmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Jiannei\LaravelCrawler\Models;

use Illuminate\Database\Eloquent\Model;

class CrawlTask extends Model
{
    protected $fillable = [
        'name',
        'expression',
        'pattern',
        'active',
    ];

    protected $casts = [
        'pattern' => 'json',
    ];

    public function records()
    {
        return $this->hasMany(CrawlRecord::class, 'task_id');
    }
}
