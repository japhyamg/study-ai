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

    // ── Members (separate tables per user type) ──

    public function admins(): HasMany { return $this->hasMany(SchoolAdmin::class); }
    public function teachers(): HasMany { return $this->hasMany(Teacher::class); }
    public function students(): HasMany { return $this->hasMany(Student::class); }

    public function terms(): HasMany { return $this->hasMany(Term::class); }
    public function subjects(): HasMany { return $this->hasMany(Subject::class); }
    public function classes(): HasMany { return $this->hasMany(ClassModel::class); }
    public function materials(): HasMany { return $this->hasMany(Material::class); }
    public function exams(): HasMany { return $this->hasMany(Exam::class); }
    public function inviteCodes(): HasMany { return $this->hasMany(InviteCode::class); }
    public function questionBank(): HasMany { return $this->hasMany(QuestionBank::class); }

    /**
     * Total member count (admins + teachers + students).
     * Works with withCount(['admins','teachers','students']) and falls back to
     * three COUNT queries when the counts are not eager loaded.
     */
    public function getMembersCountAttribute(): int
    {
        if (array_key_exists('admins_count', $this->attributes)) {
            return (int) ($this->attributes['admins_count'] ?? 0)
                + (int) ($this->attributes['teachers_count'] ?? 0)
                + (int) ($this->attributes['students_count'] ?? 0);
        }

        return $this->admins()->count() + $this->teachers()->count() + $this->students()->count();
    }

    /** The full workspace URL for this school: https://{slug}.{central-domain} (null in local dev). */
    public function appUrl(): ?string
    {
        $central = collect(config('tenancy.central_domains'))
            ->first(fn ($domain) => str_contains($domain, '.')
                && ! in_array($domain, ['localhost', '127.0.0.1'], true));

        if (! $central) {
            return null; // no real central domain configured → stay path-based
        }

        return 'https://'.$this->slug.'.'.$central;
    }
}
