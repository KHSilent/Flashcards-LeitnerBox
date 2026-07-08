<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlashcardSide extends Model
{
    use HasFactory;

    protected $fillable = [
        'flashcard_id',
        'side_number',
        'content',
        'images',
        'audios',
    ];

    protected function casts(): array
    {
        return [
            'images' => 'array',
            'audios' => 'array',
        ];
    }

    public function flashcard(): BelongsTo
    {
        return $this->belongsTo(Flashcard::class);
    }
}
