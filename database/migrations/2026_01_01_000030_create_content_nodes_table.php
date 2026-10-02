<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('content_nodes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // الربط البوليمورفيك مع الكيان الأساسي (Book, Manuscript, Audio, Video)
            $table->uuidMorphs('entity');

            // معرف الأب للشجرة الهرمية الذاتية (مجلد > باب > فصل > صفحة)
            $table->uuid('parent_id')->nullable()->index();

            // النوع والعنوان والترتيب
            $table->string('type', 50)->index(); // chapter, volume, segment, page, etc.
            $table->string('title');
            $table->string('slug')->index();
            $table->integer('order')->default(0);

            // نصوص المحتوى المعتمدة في المشروع
            $table->longText('content_html')->nullable();
            $table->longText('plain_text')->nullable();

            // حقول الـ JSONB الذكية (بديلة وثائق MongoDB وتدعم فهارس GIN والاستعلامات السريعة)
            $table->jsonb('content_json')->nullable(); // شجرة محرر Tiptap كاملة
            $table->jsonb('metadata')->nullable();     // start_time, end_time, folio_number, image_url...
            $table->jsonb('versions')->nullable();     // سجل النسخ والتعديلات التاريخية

            $table->timestamps();
            $table->softDeletes();

            // فهرس مركب فائق السرعة لتصفح واستدعاء شجرة أي كتاب أو مادة بلحظات
            $table->index(['entity_type', 'entity_id', 'parent_id', 'order'], 'idx_content_nodes_tree');

            // فهرس مركب للبحث السريع عن العقد بالـ slug داخل الكيان الواحد (مطابق لفهرس Mongo: manuscript_id + slug)
            $table->index(['entity_type', 'entity_id', 'slug'], 'idx_content_nodes_entity_slug');

            // فهرس GIN فائق السرعة للبحث والاستعلام المباشر داخل metadata (folio_number, start_time...)
            $table->index('metadata', 'idx_content_nodes_metadata_gin', 'gin');
        });

        // إضافة المفتاح الأجنبي الذاتي للشجرة بعد إنشاء الجدول
        Schema::table('content_nodes', function (Blueprint $table) {
            $table->foreign('parent_id')
                ->references('id')
                ->on('content_nodes')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_nodes');
    }
};
