<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherTokenLimit extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['user_id', 'monthly_limit', 'is_enabled'];

    protected $casts = [
        'monthly_limit' => 'integer',
        'is_enabled' => 'boolean',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
