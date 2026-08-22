<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassModel extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'classes';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'school_id', 'term_id', 'subject_id', 'teacher_id',
        'name', 'description', 'invite_code',
    ];

    public function school(): BelongsTo { return $this->belongsTo(School::class); }
    public function term(): BelongsTo { return $this->belongsTo(Term::class); }
    public function subject(): BelongsTo { return $this->belongsTo(Subject::class); }
    public function teacher(): BelongsTo { return $this->belongsTo(User::class, 'teacher_id'); }
    public function enrollments(): HasMany { return $this->hasMany(ClassEnrollment::class, 'class_id'); }
    public function materials(): HasMany { return $this->hasMany(Material::class, 'class_id'); }
    public function exams(): HasMany { return $this->hasMany(Exam::class, 'class_id'); }
    public function inviteCodes(): HasMany { return $this->hasMany(InviteCode::class); }
}
