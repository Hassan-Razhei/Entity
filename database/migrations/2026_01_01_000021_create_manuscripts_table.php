<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('manuscripts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->bigInteger('serial_number')->autoIncrement()->unique();
            
            // البيانات الأساسية
            $table->string('title');
            $table->string('slug')->unique()->index();
            $table->string('catalog_number')->nullable()->index();
            $table->string('parts')->nullable();
            $table->boolean('is_autograph')->default(false);
            $table->string('scribe')->nullable();
            $table->string('copy_date')->nullable();
            
            // الوصف والمحتوى
            $table->longText('description')->nullable();
            $table->longText('inscriptions')->nullable();
            $table->string('original_title')->nullable();
            $table->longText('manuscript_start')->nullable();
            $table->longText('manuscript_end')->nullable();
            $table->string('code')->nullable()->index();
            $table->longText('notes')->nullable();

            // الميتاداتا للنسخة الورقية
            $table->string('manuscript_century')->nullable();
            $table->string('manuscript_century_label')->nullable();
            $table->string('script_type')->nullable();
            $table->string('dimensions')->nullable();
            $table->integer('lines_per_page')->nullable();
            $table->integer('pages')->default(0); 
            $table->string('location')->nullable();
            
            // الملفات
            $table->string('cover_path')->nullable();
            $table->string('file_path')->nullable();
            
            $table->timestamps();
            $table->softDeletes();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manuscripts');
    }
};
