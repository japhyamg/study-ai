<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['school_id', 'name', 'code'];

    public function school(): BelongsTo { return $this->belongsTo(School::class); }
    public function classes(): HasMany { return $this->hasMany(ClassModel::class); }
    public function materials(): HasMany { return $this->hasMany(Material::class); }
    public function questions(): HasMany { return $this->hasMany(QuestionBank::class); }
    public function exams(): HasMany { return $this->hasMany(Exam::class); }
}
