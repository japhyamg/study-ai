<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassEnrollment extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['class_id', 'user_id', 'role', 'enrolled_at'];

    protected $casts = ['enrolled_at' => 'datetime'];

    public function class(): BelongsTo { return $this->belongsTo(ClassModel::class, 'class_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
