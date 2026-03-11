<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\BomAuditLog;
use App\Models\MbExport;
use App\Models\MbTabStatus;
use App\Models\RFQ;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class FinalizeRFQImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $projectId;
    public $bomId;
    public $filePath;
    public $logId;
    public $batchId;

    public $timeout = 300;
    public $tries = 3;
    public $backoff = 60;

    public function __construct($projectId, $bomId, $filePath, $logId, $batchId)
    {
        $this->projectId = $projectId;
        $this->bomId = $bomId;
        $this->filePath = $filePath;
        $this->logId = $logId;
        $this->batchId = $batchId;
        $this->onQueue('default');
    }

    public function handle()
    {
        Log::info("Inside FinalUpdateJOB");
        // Re-check the batch status and self-schedule until it's finished
       \Log::info('FinalizeRFQImportJob: ENTER', [
            'batch_id' => $this->batchId,
            'bom_id'   => $this->bomId,
            'log_id'   => $this->logId,
        ]);

        $batch = \Illuminate\Support\Facades\Bus::findBatch($this->batchId);

        if (!$batch) {
            \Log::warning('FinalizeRFQImportJob: Batch not found, aborting', ['batch_id' => $this->batchId]);
            return;
        }

        // If jobs are still running or not marked finished, reschedule self
        if (($batch->pendingJobs ?? 0) > 0 || is_null($batch->finishedAt)) {
            \Log::info('FinalizeRFQImportJob: Batch not finished yet, rescheduling', [
                'batch_id'     => $this->batchId,
                'pending_jobs' => $batch->pendingJobs,
                'finished_at'  => $batch->finishedAt,
            ]);

            self::dispatch($this->projectId, $this->bomId, $this->filePath, $this->logId, $this->batchId)
                ->delay(now()->addSeconds(5))
                ->onQueue('default');

            return;
        }


        $log = BomAuditLog::find($this->logId);
        if (!$log) {
            Log::error('FinalizeRFQImportJob: BomAuditLog not found', ['log_id' => $this->logId]);
            return;
        }

        MbTabStatus::where('bom_id', $this->bomId)->update(['status' => 'pending']);
        MbExport::where('bom_id', $this->bomId)->update(['status' => 'pending']);

        // Retrieve cpns from job_batches
        // $batch = Bus::findBatch($this->batchId);
        // $cpns = [];
        // if ($batch && $batch->options) {
        //     $options = is_string($batch->options) ? json_decode($batch->options, true) : ($batch->options ?? []);
        //     $cpns = $options['cpns'] ?? [];
        // } else {
        //     Log::warning('FinalizeRFQImportJob: Batch or options not found', ['batch_id' => $this->batchId]);
        // }
        // $uniqueCpns = array_unique($cpns);

       // Retrieve cpns from job_batches (authoritative for this run)
        $uniqueCpns = [];

        $batch = \Illuminate\Support\Facades\Bus::findBatch($this->batchId);

        if ($batch) {
            // $batch->options can be array (already decoded by Laravel) OR string (TEXT from DB)
            $options = $batch->options ?? [];

            if (is_string($options)) {
                $decoded = json_decode($options, true);
                $options = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : [];
            } elseif (!is_array($options)) {
                $options = [];
            }

            $cpns = isset($options['cpns']) && is_array($options['cpns']) ? $options['cpns'] : [];
            $uniqueCpns = array_values(array_unique($cpns));
        } else {
            \Log::warning('FinalizeRFQImportJob: Batch not found', ['batch_id' => $this->batchId]);
        }


        if (!empty($uniqueCpns)) {
            RFQ::where('bom_id', $this->bomId)
                ->whereIn('cpn', $uniqueCpns)
                ->update([
                    'is_cron' => false,
                    'is_L1' => false,
                    'excess_cost' => null,
                    'excess_qty' => null,
                ]);
        }

        if (!empty($log->errors)) {
            $log->status = 'completed_with_errors';
        } else {
            $log->status = 'completed';
        }

        $log->save();
    }
}
