<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An academic year, e.g. "2024/2025". Terms hang off a session, so history is
 * preserved year on year rather than terms floating free.
 */
class AcademicSession extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'school_id', 'name', 'start_date', 'end_date', 'is_current',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_current' => 'boolean',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function terms(): HasMany
    {
        return $this->hasMany(Term::class)->orderBy('sequence');
    }

    public function classArms(): HasMany
    {
        return $this->hasMany(ClassArm::class);
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }

    /** Make this the school's current session; unsets any other. */
    public function makeCurrent(): void
    {
        static::where('school_id', $this->school_id)
            ->where('id', '!=', $this->id)
            ->update(['is_current' => false]);

        $this->forceFill(['is_current' => true])->save();

        School::whereKey($this->school_id)->update(['current_session_id' => $this->id]);
    }

    public function isActive(): bool
    {
        if (! $this->start_date || ! $this->end_date) {
            return $this->is_current;
        }

        return now()->between($this->start_date, $this->end_date);
    }
}
