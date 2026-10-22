<?php

/*
 * This file is part of the jiannei/laravel-crawler.
 *
 * (c) jiannei <jiannei@sinan.fun>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('crawl_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('expression');
            $table->boolean('active')->default(true);
            $table->tinyInteger('status')->default(0);
            $table->json('pattern');
            $table->longText('exception')->nullable();
            $table->timestamp('previous_run_date')->nullable()->default(null);
            $table->timestamp('next_run_date')->nullable()->default(null);
            $table->timestamps();
        });

        Schema::create('crawl_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id');
            $table->boolean('consumed')->default(false);
            $table->json('content');
            $table->longText('exception')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crawl_records');
        Schema::dropIfExists('crawl_tasks');
    }
};
