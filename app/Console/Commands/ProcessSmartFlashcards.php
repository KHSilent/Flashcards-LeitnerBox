<?php

namespace App\Console\Commands;

use App\Models\Flashcard;
use App\Services\SmartFlashcardProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessSmartFlashcards extends Command
{
    protected $signature = 'flashcards:process-smart {--limit=5} {--stale-after=15}';

    protected $description = 'Process queued smart flashcards one by one.';

    public function handle(SmartFlashcardProcessor $processor): int
    {
        $limit = max(1, min((int) $this->option('limit'), 25));
        $staleAfter = max(5, min((int) $this->option('stale-after'), 120));

        $recovered = Flashcard::query()
            ->where('needs_ai_processing', true)
            ->where('ai_processing_status', Flashcard::AI_STATUS_PROCESSING)
            ->where('updated_at', '<=', now()->subMinutes($staleAfter))
            ->update([
                'ai_processing_status' => Flashcard::AI_STATUS_PENDING,
                'ai_processing_error' => 'The previous processing attempt was interrupted and has been queued again.',
            ]);

        if ($recovered > 0) {
            $this->warn("Recovered {$recovered} interrupted flashcard(s).");
        }

        $ids = Flashcard::query()
            ->where('needs_ai_processing', true)
            ->where('ai_processing_status', Flashcard::AI_STATUS_PENDING)
            ->whereIn('type', [Flashcard::TYPE_ENGLISH_ACTIVE, Flashcard::TYPE_ENGLISH_PASSIVE])
            ->orderBy('ai_processing_requested_at')
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        foreach ($ids as $id) {
            $claimed = Flashcard::query()
                ->whereKey($id)
                ->where('needs_ai_processing', true)
                ->where('ai_processing_status', Flashcard::AI_STATUS_PENDING)
                ->update([
                    'ai_processing_status' => Flashcard::AI_STATUS_PROCESSING,
                    'ai_processing_error' => null,
                ]);

            if ($claimed !== 1) {
                continue;
            }

            try {
                $flashcard = Flashcard::query()->with('sides')->findOrFail($id);
                $processor->process($flashcard);

                Flashcard::query()
                    ->whereKey($id)
                    ->update([
                        'needs_ai_processing' => false,
                        'ai_processing_status' => Flashcard::AI_STATUS_DONE,
                        'ai_processed_at' => now(),
                        'ai_processing_failed_at' => null,
                        'ai_processing_error' => null,
                    ]);

                $this->info("Processed flashcard {$id}");
            } catch (Throwable $exception) {
                Flashcard::query()
                    ->whereKey($id)
                    ->update([
                        'needs_ai_processing' => false,
                        'ai_processing_status' => Flashcard::AI_STATUS_FAILED,
                        'ai_processing_failed_at' => now(),
                        'ai_processing_error' => mb_substr($exception->getMessage(), 0, 2000),
                    ]);

                Log::warning('flashcard.smart_process.failed', [
                    'flashcard_id' => $id,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);

                $this->warn("Failed flashcard {$id}: {$exception->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
