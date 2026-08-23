<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry in a material's review conversation — the teacher's submission
 * message, an admin's change request, or the reason for a rejection.
 *
 * Kept as an append-only trail so "why was this sent back?" always has an
 * answer, even after the material has been resubmitted and approved.
 */
class SubmissionNote extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['material_id', 'user_id', 'note_type', 'content'];

    const TYPE_SUBMISSION = 'submission';

    const TYPE_CHANGE_REQUEST = 'change_request';

    const TYPE_ADMIN_NOTE = 'admin_note';

    const TYPE_APPROVAL = 'approval';

    const TYPE_REJECTION = 'rejection';

    public const TYPE_LABELS = [
        self::TYPE_SUBMISSION => 'Submitted for review',
        self::TYPE_CHANGE_REQUEST => 'Changes requested',
        self::TYPE_ADMIN_NOTE => 'Note',
        self::TYPE_APPROVAL => 'Approved',
        self::TYPE_REJECTION => 'Rejected',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        return self::TYPE_LABELS[$this->note_type] ?? ucfirst(str_replace('_', ' ', (string) $this->note_type));
    }

    public function tone(): string
    {
        return match ($this->note_type) {
            self::TYPE_APPROVAL => 'ok',
            self::TYPE_REJECTION => 'danger',
            self::TYPE_CHANGE_REQUEST => 'warn',
            default => '',
        };
    }
}
