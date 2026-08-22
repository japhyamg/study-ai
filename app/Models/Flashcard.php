<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Flashcard extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'user_id', 'material_id', 'front', 'back', 'tags',
        'review_status', 'ease_factor', 'interval', 'repetitions',
        'lapses', 'due_date', 'last_review', 'review_count',
    ];

    protected $casts = [
        'tags' => 'array',
        'due_date' => 'datetime',
        'last_review' => 'datetime',
        'ease_factor' => 'float',
        'interval' => 'integer',
        'repetitions' => 'integer',
        'lapses' => 'integer',
        'review_count' => 'integer',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function material(): BelongsTo { return $this->belongsTo(Material::class); }
}
