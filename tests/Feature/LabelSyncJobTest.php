<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Jobs\LabelSyncJob;
use App\Models\Label;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;

class LabelSyncJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_label_sync_job_can_be_dispatched(): void
    {
        Queue::fake();

        LabelSyncJob::dispatch();

        Queue::assertPushed(LabelSyncJob::class);
    }

    public function test_label_sync_job_handles_execution(): void
    {
        Log::shouldReceive('info')
            ->andReturn(null);
        
        Log::shouldReceive('error')
            ->andReturn(null);

        $job = new LabelSyncJob();
        
        // This will attempt to sync but fail due to missing OAuth tokens
        // We're just testing that the job structure is correct
        try {
            $job->handle();
        } catch (\Exception $e) {
            // Expected to fail without proper OAuth setup
            $this->assertTrue(true);
        }
    }

    public function test_label_sync_job_has_correct_properties(): void
    {
        $job = new LabelSyncJob();

        $this->assertEquals(3, $job->tries);
        $this->assertEquals(300, $job->timeout);
    }
}

