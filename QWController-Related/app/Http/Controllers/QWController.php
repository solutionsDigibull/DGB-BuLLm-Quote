<?php

namespace App\Http\Controllers;

use App\Imports\RFQImport;
use App\Jobs\RFQImportJob;
use Illuminate\Http\Request;
use App\Models\Project; // Assuming you have a Project model
use App\Models\BOM; // Assuming you have a BOM model
use App\Models\BomDetail;
use App\Models\BomUpload;
use App\Models\RFQUpload;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

class QWController extends Controller
{
    public function create(Request $request, $project_id, $bom_id)
    {
        try {
            // Check if the BOM is valid
            $isValidBom = BomDetail::where('is_valid_bom', true)
                ->where('bom_upload_id', $bom_id)
                ->exists();

            if (!$isValidBom) {
                return redirect()->back()->withErrors(['error' => 'Bom is not valid']);
            }

            // Fetch BOM details and project details
            $bomDetails = BomUpload::where('id', $bom_id)
                ->where('project_id', $project_id)
                ->first(['id', 'project_id', 'folder_name', 'file_name']);

            if (!$bomDetails) {
                throw new \Exception('BOM or Project not found.');
            }

            // Render the view with BOM details
            return view('crm.qw.create', [
                'title' => 'Upload Third Party RFQ',
                'subtitle' => 'Upload Excel File',
                'bomDetails' => $bomDetails, // Pass BOM details to the view
            ]);
        } catch (\Exception $e) {
            // Log the exception for debugging
            Log::error('Error in QWController@create: ' . $e->getMessage(), [
                'project_id' => $project_id,
                'bom_id' => $bom_id,
            ]);

            // Redirect back with error message
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }


    public function store(Request $request)
    {
        try {
            // 1. Validate file
            $request->validate([
                'file' => 'required|file|mimes:xlsx,xls,csv|max:20480',
            ]);

            $file = $request->file('file');
            $fileName = $file->getClientOriginalName();
            $folder = 'uploads/rfq_files';
            $newFileName = time() . '_' . $fileName;
            $s3Path = $folder . '/' . $newFileName;

            // 2. Upload to S3
            Storage::disk('s3')->putFileAs($folder, $file, $newFileName, ['visibility' => 'private']);

            Log::info('Uploading RFQ:', [
                'project_id' => $request->project_id,
                'bom_id' => $request->bom_id,
                'file_name' => $fileName,
                's3_path' => $s3Path,
            ]);

            // 3. === VALIDATE VOLUME COUNT BEFORE DISPATCH ===
            $tempPath = $this->downloadFromS3ToTemp($s3Path);
            $volumeCountInFile = $this->countAwardColumns($tempPath);
            $volumeCountInDb = $this->getBomVolumeCount($request->bom_id);

            if ($volumeCountInFile !== $volumeCountInDb) {
                @unlink($tempPath); // clean up

                $error = "Volume count mismatch. BOM expects {$volumeCountInDb} volume(s), but file contains {$volumeCountInFile} volume(s). Please re-upload the Scrub BOM with correct volume count and try again.";
                Log::error('RFQ upload failed - volume mismatch', [
                    'bom_id' => $request->bom_id,
                    'expected' => $volumeCountInDb,
                    'found' => $volumeCountInFile,
                ]);

                return redirect()->back()->withErrors(['error' => $error]);
            }

            @unlink($tempPath); // clean up

            // 4. Only now dispatch the job
            RFQImportJob::dispatch(auth()->id(), $request->project_id, $request->bom_id, $s3Path);

            return redirect()->back()->with('success', 'File upload queued successfully!');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());

        } catch (\Exception $e) {
            Log::error('Error uploading RFQ file:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()->withErrors([
                'error' => 'File upload failed. ' . $e->getMessage()
            ]);
        }
    }

    private function downloadFromS3ToTemp(string $s3Path): string
    {
        $ext = pathinfo($s3Path, PATHINFO_EXTENSION) ?: 'xlsx';
        $tmpBase = tempnam(sys_get_temp_dir(), 'rfq_validate_');
        $tmpPath = $tmpBase . '.' . $ext;
        @unlink($tmpBase);

        $stream = Storage::disk('s3')->readStream($s3Path);
        $out = fopen($tmpPath, 'w');
        stream_copy_to_stream($stream, $out);
        fclose($stream);
        fclose($out);

        return $tmpPath;
    }

    private function countAwardColumns(string $filePath): int
    {
        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();

        $headerRow = $worksheet->rangeToArray('A17:' . $worksheet->getHighestColumn() . '17', null, true, false, false)[0];
        $headers = array_map('trim', $headerRow);

        $count = 0;
        foreach ($headers as $header) {
            if (preg_match('/^Award\s*#\d+$/i', $header)) {
                $count++;
            }
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $count;
    }

    private function getBomVolumeCount(int $bomId): int
    {
       $baseCount = (int) \DB::table('bom_details')
        ->where('bom_upload_id', $bomId)
        ->value('volume_count') ?? 0;

        $hasProto = \DB::table('bom_details')
            ->where('bom_upload_id', $bomId)
            ->where('proto', '>', 0)
            ->exists();

        return $hasProto ? $baseCount + 1 : $baseCount;
    }
}
