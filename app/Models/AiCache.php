<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiCache extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'ai_cache';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['content_hash', 'response', 'expires_at'];

    protected $casts = [
        'response' => 'array',
        'expires_at' => 'datetime',
    ];
}
