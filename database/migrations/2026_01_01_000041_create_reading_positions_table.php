<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reading_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained()->onDelete('cascade');
            $table->uuidMorphs('entity');
            $table->string('node_slug')->nullable();
            $table->integer('scroll_offset')->default(0);
            $table->integer('timestamp')->nullable(); // For Audio/Video
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'entity_id', 'entity_type'], 'user_entity_reading_pos_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reading_positions');
    }
};
