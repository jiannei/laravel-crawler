<?php

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
        'pattern' => 'json'
    ];

    public function records()
    {
        return $this->hasMany(CrawlRecord::class,'task_id');
    }
}
