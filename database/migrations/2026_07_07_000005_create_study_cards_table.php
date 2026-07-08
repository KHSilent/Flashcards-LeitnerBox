<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('study_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_access_id')->constrained()->cascadeOnDelete();
            $table->foreignId('flashcard_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('step_index')->default(0);
            $table->timestamp('entered_at')->useCurrent();
            $table->timestamp('due_at')->useCurrent();
            $table->timestamps();

            $table->unique(['category_access_id', 'flashcard_id']);
            $table->index(['category_access_id', 'step_index', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_cards');
    }
};
