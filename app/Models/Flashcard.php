<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Flashcard extends Model
{
    use HasFactory;

    public const TYPE_ENGLISH_ACTIVE = 'english-active';

    public const TYPE_ENGLISH_PASSIVE = 'english-passive';

    public const TYPE_OTHER = 'other';

    public const TYPES = [
        self::TYPE_ENGLISH_ACTIVE,
        self::TYPE_ENGLISH_PASSIVE,
        self::TYPE_OTHER,
    ];

    public const DEFAULT_TYPE = self::TYPE_OTHER;

    public const AI_STATUS_PENDING = 'pending';

    public const AI_STATUS_PROCESSING = 'processing';

    public const AI_STATUS_DONE = 'done';

    public const AI_STATUS_FAILED = 'failed';

    protected $fillable = [
        'flashcard_category_id',
        'title',
        'type',
        'needs_ai_processing',
        'ai_processing_status',
        'ai_processing_requested_at',
        'ai_processed_at',
        'ai_processing_failed_at',
        'ai_processing_error',
    ];

    protected function casts(): array
    {
        return [
            'needs_ai_processing' => 'boolean',
            'ai_processing_requested_at' => 'datetime',
            'ai_processed_at' => 'datetime',
            'ai_processing_failed_at' => 'datetime',
        ];
    }

    public function supportsAiProcessing(): bool
    {
        return in_array($this->type, [self::TYPE_ENGLISH_ACTIVE, self::TYPE_ENGLISH_PASSIVE], true);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FlashcardCategory::class, 'flashcard_category_id');
    }

    public function sides(): HasMany
    {
        return $this->hasMany(FlashcardSide::class)->orderBy('side_number');
    }

    public function studyCards(): HasMany
    {
        return $this->hasMany(StudyCard::class);
    }
}
