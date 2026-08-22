<?php

namespace Tests\Unit;

use App\Services\SrsService;
use PHPUnit\Framework\TestCase;

class SrsServiceTest extends TestCase
{
    private SrsService $srs;

    protected function setUp(): void
    {
        $this->srs = new SrsService();
    }

    public function test_sm2_first_success_increases_interval_to_one(): void
    {
        $r = $this->srs->calculateSm2(5, 2.5, 0, 0);
        $this->assertSame(1, $r['interval']);
        $this->assertSame(1, $r['repetitions']);
        $this->assertEqualsWithDelta(2.6, $r['ease_factor'], 0.001);
    }

    public function test_sm2_second_success_intervals_six(): void
    {
        $r = $this->srs->calculateSm2(5, 2.5, 1, 1);
        $this->assertSame(6, $r['interval']);
        $this->assertSame(2, $r['repetitions']);
    }

    public function test_sm2_failure_resets_repetitions(): void
    {
        $r = $this->srs->calculateSm2(2, 2.5, 6, 4);
        $this->assertSame(0, $r['repetitions']);
        $this->assertSame(1, $r['interval']);
        $this->assertLessThan(2.5, $r['ease_factor']);
    }

    public function test_fsrs_new_card_good_goes_to_learning(): void
    {
        $card = ['ease_factor' => 2.5, 'interval' => 0, 'repetitions' => 0, 'lapses' => 0, 'state' => SrsService::STATE_NEW, 'due_date' => now(), 'last_review' => null];
        $out = $this->srs->calculateFsrs($card, SrsService::RATING_GOOD);
        $this->assertSame(SrsService::STATE_LEARNING, $out['state']);
        $this->assertGreaterThanOrEqual(1, $out['interval']);
    }

    public function test_fsrs_review_easy_grows_interval_and_ease(): void
    {
        $card = ['ease_factor' => 2.5, 'interval' => 6, 'repetitions' => 2, 'lapses' => 0, 'state' => SrsService::STATE_REVIEW, 'due_date' => now(), 'last_review' => now()];
        $out = $this->srs->calculateFsrs($card, SrsService::RATING_EASY);
        $this->assertSame(3, $out['repetitions']);
        $this->assertGreaterThan(2.5, $out['ease_factor']);
        $this->assertGreaterThan(6, $out['interval']);
        $this->assertTrue($out['due_date']->greaterThan(now()));
    }
}
