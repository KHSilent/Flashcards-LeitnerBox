<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudyCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_access_id',
        'flashcard_id',
        'step_index',
        'entered_at',
        'due_at',
    ];

    protected function casts(): array
    {
        return [
            'entered_at' => 'datetime',
            'due_at' => 'datetime',
        ];
    }

    public function access(): BelongsTo
    {
        return $this->belongsTo(CategoryAccess::class, 'category_access_id');
    }

    public function flashcard(): BelongsTo
    {
        return $this->belongsTo(Flashcard::class);
    }
}
