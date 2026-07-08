<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flashcard_sides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flashcard_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('side_number');
            $table->text('content');
            $table->json('images')->nullable();
            $table->json('audios')->nullable();
            $table->timestamps();

            $table->unique(['flashcard_id', 'side_number']);
            $table->index('flashcard_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flashcard_sides');
    }
};
