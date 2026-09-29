<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. المؤلفون (Authors Pivot)
        Schema::create('authorables', function (Blueprint $table) {
            $table->uuidMorphs('authorable');
            $table->foreignUuid('author_id')->references('id')->on('authors')->onDelete('cascade');
            $table->primary(['authorable_id', 'authorable_type', 'author_id']);
        });

        // 2. المساهمون والمحققون (Bookers Pivot)
        Schema::create('bookables', function (Blueprint $table) {
            $table->foreignUuid('booker_id')->references('id')->on('bookers')->onDelete('cascade');
            $table->uuidMorphs('bookable');
            $table->string('role')->default('contributor');
            $table->index(['booker_id', 'bookable_type']);
        });

        // 3. التصنيفات (Categories Pivot)
        Schema::create('categorizables', function (Blueprint $table) {
            $table->foreignUuid('category_id')->references('id')->on('categories')->onDelete('cascade');
            $table->uuidMorphs('entity');
            $table->timestamps();

            $table->index(['entity_id', 'entity_type']);
            $table->index('category_id');
            $table->unique(['category_id', 'entity_id', 'entity_type'], 'categorizable_unique');
        });

        // 4. الوسوم (Tags Pivot)
        Schema::create('taggables', function (Blueprint $table) {
            $table->foreignUuid('tag_id')->references('id')->on('tags')->onDelete('cascade');
            $table->uuidMorphs('entity');
            $table->timestamps();

            $table->index(['entity_id', 'entity_type']);
            $table->index('tag_id');
            $table->unique(['tag_id', 'entity_id', 'entity_type'], 'taggable_unique');
        });

        // 5. المجموعات (Collections Pivot)
        Schema::create('collectables', function (Blueprint $table) {
            $table->foreignUuid('collection_id')->references('id')->on('collections')->onDelete('cascade');
            $table->uuidMorphs('entity');
            $table->integer('order_column')->default(0);
            $table->timestamp('added_at')->nullable();

            $table->index(['entity_id', 'entity_type']);
            $table->unique(['collection_id', 'entity_id', 'entity_type'], 'collectable_unique');
        });

        // 6. السلاسل (Series Pivot)
        Schema::create('seriables', function (Blueprint $table) {
            $table->foreignUuid('series_id')->references('id')->on('series')->onDelete('cascade');
            $table->uuidMorphs('entity');
            $table->integer('position')->default(0);

            $table->index(['entity_id', 'entity_type']);
            $table->unique(['series_id', 'entity_id', 'entity_type'], 'seriable_unique');
        });

        // 7. المواضيع للكتب (Book Topics)
        Schema::create('book_topic', function (Blueprint $table) {
            $table->foreignUuid('book_id')->references('id')->on('books')->onDelete('cascade');
            $table->foreignUuid('topic_id')->references('id')->on('topics')->onDelete('cascade');
            $table->primary(['book_id', 'topic_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_topic');
        Schema::dropIfExists('seriables');
        Schema::dropIfExists('collectables');
        Schema::dropIfExists('taggables');
        Schema::dropIfExists('categorizables');
        Schema::dropIfExists('bookables');
        Schema::dropIfExists('authorables');
    }
};
