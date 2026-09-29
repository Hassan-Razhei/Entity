<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('audios', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->bigInteger('serial_number')->autoIncrement()->unique();
            $table->string('title');
            $table->string('code')->nullable()->index();
            $table->string('slug')->unique()->index();
            $table->integer('duration')->default(0);
            $table->string('format')->default('mp3')->index();
            $table->integer('bitrate')->nullable();
            $table->integer('sample_rate')->nullable();
            $table->integer('file_size')->nullable();
            $table->longText('description')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('file_path')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audios');
    }
};
