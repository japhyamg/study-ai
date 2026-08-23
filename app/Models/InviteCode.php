<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InviteCode extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'school_id', 'code', 'class_arm_id', 'max_uses', 'used_count', 'expires_at',
    ];

    protected $casts = ['expires_at' => 'datetime'];

    public function school(): BelongsTo { return $this->belongsTo(School::class); }
    public function classArm(): BelongsTo { return $this->belongsTo(ClassArm::class, 'class_arm_id'); }
}
