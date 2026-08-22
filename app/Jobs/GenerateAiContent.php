<?php

namespace App\Jobs;

use App\Models\ProcessingJob;
use App\Services\AiContentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateAiContent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ProcessingJob $job)
    {
    }

    public function handle(AiContentService $service): void
    {
        $service->runJob($this->job);
    }
}
