<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. التعليقات (Comments)
        Schema::create('comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuidMorphs('entity');
            $table->text('content');
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            $table->uuid('parent_id')->nullable()->index();
            $table->timestamps();

            $table->index(['entity_id', 'entity_type']);
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('comments')->onDelete('cascade');
        });

        // 2. الملاحظات (Notes)
        Schema::create('notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuidMorphs('entity');
            $table->text('content');
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->index(['entity_id', 'entity_type']);
        });

        // 3. سجل الأنشطة (Activities)
        Schema::create('activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuidMorphs('entity');
            $table->string('activity_type');
            $table->text('description')->nullable();
            $table->jsonb('changes')->nullable();
            $table->timestamps();

            $table->index(['entity_id', 'entity_type']);
            $table->index('activity_type');
            $table->index('user_id');
        });

        // 4. سجل المحذوفات (Deletions)
        Schema::create('deletions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuidMorphs('entity');
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->jsonb('data')->nullable();
            $table->timestamp('deleted_at')->useCurrent();

            $table->index(['entity_id', 'entity_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deletions');
        Schema::dropIfExists('activities');
        Schema::dropIfExists('notes');
        Schema::dropIfExists('comments');
    }
};
