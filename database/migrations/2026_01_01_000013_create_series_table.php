<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('series', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->bigInteger('serial_number')->autoIncrement()->unique();
            $table->string('title');
            $table->string('slug')->nullable()->index();
            $table->text('description')->nullable();
            $table->integer('order_column')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('series');
    }
};
