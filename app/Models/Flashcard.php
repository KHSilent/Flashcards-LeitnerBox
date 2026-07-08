<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Flashcard extends Model
{
    use HasFactory;

    protected $fillable = [
        'flashcard_category_id',
        'title',
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
