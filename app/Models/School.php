<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class School extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['name', 'slug', 'logo'];

    public function members(): HasMany { return $this->hasMany(SchoolMember::class); }
    public function terms(): HasMany { return $this->hasMany(Term::class); }
    public function subjects(): HasMany { return $this->hasMany(Subject::class); }
    public function classes(): HasMany { return $this->hasMany(ClassModel::class); }
    public function materials(): HasMany { return $this->hasMany(Material::class); }
    public function exams(): HasMany { return $this->hasMany(Exam::class); }
    public function inviteCodes(): HasMany { return $this->hasMany(InviteCode::class); }
    public function questionBank(): HasMany { return $this->hasMany(QuestionBank::class); }
}
