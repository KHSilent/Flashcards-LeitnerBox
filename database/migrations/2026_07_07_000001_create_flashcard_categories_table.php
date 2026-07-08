<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flashcard_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('flashcard_categories')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->timestamps();

            $table->unique(['parent_id', 'name']);
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flashcard_categories');
    }
};
