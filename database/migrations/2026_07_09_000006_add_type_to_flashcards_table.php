<?php

use App\Models\Flashcard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('flashcards', 'type')) {
            return;
        }

        Schema::table('flashcards', function (Blueprint $table) {
            $table->enum('type', Flashcard::TYPES)
                ->default(Flashcard::DEFAULT_TYPE)
                ->after('title')
                ->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('flashcards', 'type')) {
            return;
        }

        Schema::table('flashcards', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });
    }
};
