<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Question extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'material_id', 'question', 'type', 'options', 'correct_idx',
        'explanation', 'difficulty', 'tags', 'review_status',
    ];

    protected $casts = [
        'options' => 'array',
        'tags' => 'array',
        'correct_idx' => 'integer',
        'difficulty' => 'integer',
    ];

    public function material(): BelongsTo { return $this->belongsTo(Material::class); }
}
