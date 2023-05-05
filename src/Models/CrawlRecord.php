<?php

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
