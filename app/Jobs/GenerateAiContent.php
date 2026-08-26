<?php

namespace App\Jobs;

use App\Models\Material;
use App\Models\ProcessingJob;
use App\Services\AiContentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs the AI pipeline for one material off the request cycle.
 *
 * `tries = 1` on purpose. AiService already retries the transient failures
 * (429s, 5xx) with backoff, and a job-level retry would re-run generation that
 * partially succeeded — spending the teacher's tokens a second time to produce
 * the same rows. A failed job is surfaced in the UI for a human to retry.
 */
class GenerateAiContent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    /** Generous: a full run is several sequential model calls. */
    public int $timeout = 900;

    public function __construct(public ProcessingJob $job) {}

    public function handle(AiContentService $service): void
    {
        $service->runJob($this->job);
    }

    /**
     * Reached when the worker itself dies — timeout, OOM, deploy mid-run.
     * runJob's own catch cannot fire here, so the records would otherwise sit
     * in "processing" forever.
     */
    public function failed(?Throwable $e): void
    {
        Log::error('AI generation job failed', [
            'job_id' => $this->job->id,
            'error' => $e?->getMessage(),
        ]);

        $this->job->refresh()->update([
            'status' => ProcessingJob::STATUS_FAILED,
            'error' => $e?->getMessage() ?? 'The generation worker stopped unexpectedly.',
            'completed_at' => now(),
        ]);

        $this->job->material?->transitionTo(Material::STATE_AI_FAILED);
    }
}
