<?php

namespace App\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Models\BomAuditLog;
use App\Models\BomDetail;
use Illuminate\Support\Facades\Storage;
use App\Models\Project;
use Illuminate\Bus\Batch;

class RFQImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $userId;
    public $projectId;
    public $bomId;
    public $filePath;

    public $timeout = 600;
    public $tries = 3;
    public $backoff = 60;

    public function __construct($userId, $projectId, $bomId, $filePath)
    {
        $this->userId = $userId;
        $this->projectId = $projectId;
        $this->bomId = $bomId;
        $this->filePath = $filePath;
        $this->onQueue('default');
    }

    public function handle()
    {
        try {
            $projectId = $this->projectId;
            $bomId = $this->bomId;
            $filePath = $this->filePath;

             // --- Read from S3 into a local temp file with correct extension ---
            $ext = pathinfo($filePath, PATHINFO_EXTENSION) ?: 'xlsx';
            $tmpBase = tempnam(sys_get_temp_dir(), 'rfq_');
            $tmpPath = $tmpBase . '.' . $ext;
            @unlink($tmpBase); // rename strategy: create new file with extension

            $in  = Storage::disk('s3')->readStream($filePath);
            if (!$in) {
                throw new \RuntimeException("Unable to read S3 object: {$filePath}");
            }
            $out = fopen($tmpPath, 'w');
            stream_copy_to_stream($in, $out);
            if (is_resource($in)) fclose($in);
            if (is_resource($out)) fclose($out);
            // ---------------------------------------------------------------

            $reader = IOFactory::createReaderForFile($tmpPath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($tmpPath);
            $worksheet = $spreadsheet->getActiveSheet();

            // 🔧 Use data bounds (ignores formatted-but-empty tails)
            $highestDataRow    = $worksheet->getHighestDataRow();     // last row with real data
            $highestDataColumn = $worksheet->getHighestDataColumn();  // last column with real data

            Log::info("RFQImportJob: Data bounds detected - Row: {$highestDataRow}, Column: {$highestDataColumn}");
            // Your header row is fixed at 17; clamp end-row to be >= 17
            $highestRow    = max($highestDataRow, 17);
            $highestColumn = $highestDataColumn;

            // Read headers from row 17 using data-based last column
            $headerRow = $worksheet->rangeToArray('A17:' . $highestColumn . '17', null, true, false, false)[0];
            $headers = array_map('trim', $headerRow);

            $dynamicGroups = ['Cost', 'Price', 'Awarded Volume', 'Award', 'Source'];
            $dynamicHeaders = [];
            foreach ($dynamicGroups as $groupBase) {
                $dynamicHeaders[$groupBase] = [];
                foreach ($headers as $index => $header) {
                    if ($groupBase === 'Price' && (stripos($header, 'Price Type') !== false)) {
                        continue;
                    }
                    if (strpos($header, $groupBase) !== false) {
                        preg_match('/\d+/', $header, $matches);
                        $groupIndex = $matches[0] ?? 1;
                        $dynamicHeaders[$groupBase][$groupIndex] = $index;
                    }
                }
            }

            $log = BomAuditLog::create([
                'user_id' => $this->userId,
                'folder_name' => 'Uploads',
                'file_name' => basename($this->filePath),
                'version' => '1.0',
                'status' => 'completed',
                'project_id' => $this->projectId,
                'bom_upload_id' => $this->bomId,
                'errors' => [],
            ]);
            $logId = $log->id;

            // Chunk based on highest *data* row
            $startRow = 18;
            $chunkSize = 100;
            $jobs = [];

            $bomDetail = BomDetail::where('bom_upload_id', $this->bomId)
                            ->select('proto')
                            ->first();

                        $proto = $bomDetail->proto ?? 0;
                        $hasProto = $proto > 0;

                        Log::info('RFQImportJob: Proto check', [
                            'bom_id' => $this->bomId,
                            'proto'  => $proto,
                            'hasProto' => $hasProto,
                        ]);

            for ($row = $startRow; $row <= $highestRow; $row += $chunkSize) {
                $endRow = min($row + $chunkSize - 1, $highestRow);
                $jobs[] = new RFQChunkProcessJob(
                    $this->projectId,
                    $this->bomId,
                    $headers,
                    $dynamicHeaders,
                    $filePath,
                    $row,
                    $endRow,
                    $highestColumn,
                    $logId,
                    $proto,        // <-- NEW
                    $hasProto      // <-- NEW
                );
            }

             // free memory
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
            @unlink($tmpPath);

            // Bus::batch($jobs)
            //     ->then(function ($batch) use ($logId, $projectId, $bomId, $filePath) {
            //         FinalizeRFQImportJob::dispatch($projectId, $bomId, $filePath, $logId, $batch->id);
            //     })
            //     ->catch(function ($batch, $e) use ($logId, $projectId, $bomId, $filePath) {
            //         Log::error('Batch failed, dispatching FinalizeRFQImportJob', [
            //             'batch_id' => $batch->id,
            //             'error' => $e->getMessage(),
            //         ]);
            //         FinalizeRFQImportJob::dispatch($projectId, $bomId, $filePath, $logId, $batch->id);
            //     })
            //     ->onQueue('default')
            //     ->dispatch();
           $batch = \Illuminate\Support\Facades\Bus::batch($jobs)
            ->then(function (\Illuminate\Bus\Batch $batch) {
                \Illuminate\Support\Facades\Log::info('RFQImport batch THEN (success)', ['batch_id' => $batch->id]);
            })
            ->catch(function (\Illuminate\Bus\Batch $batch, \Throwable $e) {
                \Illuminate\Support\Facades\Log::error('RFQImport batch CATCH (failure)', [
                    'batch_id' => $batch->id,
                    'error'    => $e->getMessage(),
                ]);
            })
            ->finally(function (\Illuminate\Bus\Batch $batch) use ($logId, $projectId, $bomId, $filePath) {
                \Illuminate\Support\Facades\Log::info('RFQImport batch FINALLY: dispatching FinalizeRFQImportJob', [
                    'batch_id' => $batch->id,
                ]);
                \App\Jobs\FinalizeRFQImportJob::dispatch($projectId, $bomId, $filePath, $logId, $batch->id)
                    ->onQueue('default');
            })
            ->onQueue('default')
            ->dispatch();

        // 🔎 log batch meta right after dispatch
        \Illuminate\Support\Facades\Log::info('RFQImportJob: batch DISPATCHED', [
            'batch_id'     => $batch->id,
            'total_jobs'   => $batch->totalJobs,
            'pending_jobs' => $batch->pendingJobs,
        ]);

        \App\Jobs\FinalizeRFQImportJob::dispatch($projectId, $bomId, $filePath, $logId, $batch->id)
            ->delay(now()->addSeconds(5))
            ->onQueue('default');

        } catch (\Exception $e) {
            Log::error('Error in RFQImportJob', ['error' => $e->getMessage()]);
            try {
                BomAuditLog::create([
                    'user_id' => $this->userId,
                    'folder_name' => 'Uploads',
                    'file_name' => basename($this->filePath),
                    'version' => '1.0',
                    'status' => 'completed_with_errors',
                    'project_id' => $this->projectId,
                    'bom_upload_id' => $this->bomId,
                    'errors' => [['error' => $e->getMessage()]],
                ]);
            } catch (\Exception $logError) {
                Log::error('Failed to create fallback BomAuditLog', ['error' => $logError->getMessage()]);
            }
        }
    }
}
