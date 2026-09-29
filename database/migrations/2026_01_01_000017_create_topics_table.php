<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('topics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->bigInteger('serial_number')->autoIncrement()->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->uuid('parent_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('topics', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('topics')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topics');
    }
};
