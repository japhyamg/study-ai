<?php

namespace App\Services;

use App\Models\PlatformSetting;
use App\Models\TeacherTokenLimit;
use App\Models\TokenUsage;
use Exception;

class TokenLimitError extends Exception
{
    public function __construct(public int $monthlyLimit, public int $remaining)
    {
        parent::__construct('Monthly AI token limit reached — contact your administrator.');
    }
}

/**
 * Teacher AI token-budget enforcement, ported from src/lib/limits/token-limits.ts.
 */
class TokenLimitService
{
    public const DEFAULT_MONTHLY_LIMIT = 1_000_000;
    private const PLATFORM_LIMIT_KEY = 'teacher_default_monthly_limit';

    private function startOfMonth(): string
    {
        return now()->startOfMonth()->toDateTimeString();
    }

    public function getPlatformDefaultLimit(): int
    {
        $row = PlatformSetting::where('key', self::PLATFORM_LIMIT_KEY)->value('value');
        $parsed = (int) $row;
        return ($parsed <= 0) ? self::DEFAULT_MONTHLY_LIMIT : $parsed;
    }

    public function setPlatformDefaultLimit(int $limit): void
    {
        $safe = max(1, (int) $limit);
        PlatformSetting::updateOrCreate(
            ['key' => self::PLATFORM_LIMIT_KEY],
            ['value' => (string) $safe]
        );
    }

    public function getTeacherTokenLimit(string $userId): array
    {
        $row = TeacherTokenLimit::where('user_id', $userId)->first();
        $defaultLimit = $this->getPlatformDefaultLimit();

        $monthlyLimit = $row?->monthly_limit ?? $defaultLimit;
        $isEnabled = $row ? (bool) $row->is_enabled : true;

        $usedThisMonth = (int) TokenUsage::where('user_id', $userId)
            ->where('created_at', '>=', $this->startOfMonth())
            ->sum('total_tokens');

        $remaining = max(0, $monthlyLimit - $usedThisMonth);

        return [
            'isEnabled' => $isEnabled,
            'monthlyLimit' => $monthlyLimit,
            'usedThisMonth' => $usedThisMonth,
            'remaining' => $remaining,
            'resetDate' => $this->startOfMonth(),
        ];
    }

    /**
     * Throws TokenLimitError if the teacher has exhausted their monthly budget.
     */
    public function assertTeacherTokenBudget(?string $userId): array
    {
        if (!$userId) {
            return [
                'isEnabled' => false,
                'monthlyLimit' => self::DEFAULT_MONTHLY_LIMIT,
                'usedThisMonth' => 0,
                'remaining' => self::DEFAULT_MONTHLY_LIMIT,
                'resetDate' => $this->startOfMonth(),
            ];
        }

        $limit = $this->getTeacherTokenLimit($userId);
        if ($limit['isEnabled'] && $limit['usedThisMonth'] >= $limit['monthlyLimit']) {
            throw new TokenLimitError($limit['monthlyLimit'], $limit['remaining']);
        }
        return $limit;
    }

    public function setTeacherTokenLimit(string $userId, ?int $monthlyLimit = null, ?bool $isEnabled = null): void
    {
        $updates = ['updated_at' => now()];
        if ($monthlyLimit !== null) {
            $updates['monthly_limit'] = max(1, (int) $monthlyLimit);
        }
        if ($isEnabled !== null) {
            $updates['is_enabled'] = $isEnabled;
        }

        $existing = TeacherTokenLimit::where('user_id', $userId)->first();
        if ($existing) {
            $existing->update($updates);
        } else {
            TeacherTokenLimit::create([
                'user_id' => $userId,
                'monthly_limit' => $monthlyLimit ?? $this->getPlatformDefaultLimit(),
                'is_enabled' => $isEnabled ?? true,
            ]);
        }
    }
}
