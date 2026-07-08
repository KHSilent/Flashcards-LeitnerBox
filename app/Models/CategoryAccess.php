<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoryAccess extends Model
{
    use HasFactory;

    public const DEFAULT_STEPS = [2, 4, 8, 16, 32];

    protected $fillable = [
        'user_id',
        'flashcard_category_id',
        'can_edit',
        'steps',
    ];

    protected function casts(): array
    {
        return [
            'can_edit' => 'boolean',
            'steps' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FlashcardCategory::class, 'flashcard_category_id');
    }

    public function studyCards(): HasMany
    {
        return $this->hasMany(StudyCard::class);
    }
}
