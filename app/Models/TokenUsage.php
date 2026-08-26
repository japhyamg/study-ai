<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TokenUsage extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'token_usage';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'school_id', 'user_id', 'material_id', 'operation', 'model',
        'prompt_tokens', 'completion_tokens', 'total_tokens', 'cost',
    ];

    protected $casts = [
        'prompt_tokens' => 'integer',
        'completion_tokens' => 'integer',
        'total_tokens' => 'integer',
        'cost' => 'decimal:6',
    ];

    public function school(): BelongsTo { return $this->belongsTo(School::class); }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    /** Null for spend recorded before material attribution, or once the material is deleted. */
    public function material(): BelongsTo { return $this->belongsTo(Material::class); }

    /** Spend since the start of the current calendar month — the allowance window. */
    public function scopeThisMonth(Builder $query): Builder
    {
        return $query->where('created_at', '>=', now()->startOfMonth());
    }
}
