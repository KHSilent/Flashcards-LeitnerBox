<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('flashcards', 'needs_ai_processing')) {
            return;
        }

        Schema::table('flashcards', function (Blueprint $table) {
            $table->boolean('needs_ai_processing')->default(false)->after('type')->index();
            $table->string('ai_processing_status')->nullable()->after('needs_ai_processing')->index();
            $table->timestamp('ai_processing_requested_at')->nullable()->after('ai_processing_status');
            $table->timestamp('ai_processed_at')->nullable()->after('ai_processing_requested_at');
            $table->timestamp('ai_processing_failed_at')->nullable()->after('ai_processed_at');
            $table->text('ai_processing_error')->nullable()->after('ai_processing_failed_at');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('flashcards', 'needs_ai_processing')) {
            return;
        }

        Schema::table('flashcards', function (Blueprint $table) {
            $table->dropIndex(['needs_ai_processing']);
            $table->dropIndex(['ai_processing_status']);
            $table->dropColumn([
                'needs_ai_processing',
                'ai_processing_status',
                'ai_processing_requested_at',
                'ai_processed_at',
                'ai_processing_failed_at',
                'ai_processing_error',
            ]);
        });
    }
};
