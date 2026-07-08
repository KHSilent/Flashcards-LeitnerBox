<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_accesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('flashcard_category_id')->constrained()->cascadeOnDelete();
            $table->boolean('can_edit')->default(false);
            $table->json('steps');
            $table->timestamps();

            $table->unique(['user_id', 'flashcard_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_accesses');
    }
};
