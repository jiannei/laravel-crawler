<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crawl_tasks');
    }
};
