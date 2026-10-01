<?php

namespace App\Domain\Documents\Jobs;

use App\Domain\Documents\Models\DocumentProcessingJob;
use App\Domain\Documents\Services\DocumentIntelligencePipeline;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(
        public int $processingJobId
    ) {
    }

    public function handle(DocumentIntelligencePipeline $pipeline): void
    {
        $job = DocumentProcessingJob::with([
            'document',
            'version',
        ])->find($this->processingJobId);

        if (!$job) {
            return;
        }

        $job->update([
            'status' => 'processing',
            'started_at' => now(),
            'attempts' => $job->attempts + 1,
        ]);

        $job->document()->update([
            'processing_status' => 'processing',
        ]);

        try {
            $force = (bool) data_get($job->result, 'force_reprocess', false);

            $extraction = $pipeline->process(
                $job->document,
                $job->version,
                $force
            );

            $job->update([
                'status' => 'completed',
                'result' => array_merge(
                    $job->result ?? [],
                    [
                        'intelligence' => [
                            'extraction_id' => $extraction->id,
                            'version_number' => $extraction->version_number,
                            'status' => $extraction->status instanceof \BackedEnum
                                ? $extraction->status->value
                                : $extraction->status,
                        ],
                    ]
                ),
                'finished_at' => now(),
            ]);

            $job->document()->update([
                'processing_status' => 'ready',
            ]);
            event(new \App\Domain\Scheduling\Events\LifePilotUpdated($job->document->user_id, 'processing.completed', ['document_id' => $job->document_id]));
        } catch (\Throwable $exception) {
            $job->update([
                'status' => 'failed',
                'message' => $exception->getMessage(),
                'finished_at' => now(),
            ]);

            $job->document()->update([
                'processing_status' => 'failed',
            ]);

            throw $exception;
        }
    }
}
