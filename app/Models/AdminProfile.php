<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Role profile for a school administrator. */
class AdminProfile extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id', 'school_id', 'staff_number', 'job_title',
        'department', 'office_phone', 'is_primary', 'permissions',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'permissions' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function roleLabel(): string
    {
        return $this->job_title ?: 'Administrator';
    }
}
