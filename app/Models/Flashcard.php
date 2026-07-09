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

    protected $fillable = [
        'flashcard_category_id',
        'title',
        'type',
    ];

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
