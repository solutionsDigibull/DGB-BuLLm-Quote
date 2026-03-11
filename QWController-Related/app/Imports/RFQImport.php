<?php

namespace App\Imports;

use App\Models\BomAuditLog;
use App\Models\BomData;
use App\Models\MbTabStatus;
use App\Models\Part;
use App\Models\Project;
use App\Models\RFQ;
use App\Models\RFQUpload;
use App\Models\SmtpDetail;
use App\Models\Supplier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Illuminate\Database\QueryException;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class RFQImport implements ToCollection, WithChunkReading
{
    private $projectId;
    private $bomId;
    private $filePath;

    public function __construct(int $projectId, ?int $bomId, string $filePath)
    {
        $this->projectId = $projectId;
        $this->bomId = $bomId;
        $this->filePath = $filePath;
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function collection(Collection $rows)
    {
        if ($rows->count() > 16) {
            $rows->splice(0, 16);
        }

        $headerRow = $rows->shift();
        $headers = $headerRow->toArray();

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

        $errors = [];

        // 🚀 Preload frequently used data
        $suppliersCache = Supplier::pluck('id', 'supplier_name')->toArray();
        $projectName = Project::where('id', $this->projectId)->value('name');
        $bomDataMap = BomData::where('bom_upload_id', $this->bomId)->get()->groupBy('CPN');

        $rfqRevisions = RFQ::where('bom_id', $this->bomId)
            ->select('supplier_id', 'MPN', 'cpn', \DB::raw('MAX(revision_no) as max_rev'))
            ->groupBy('supplier_id', 'MPN', 'cpn')
            ->get()
            ->keyBy(fn($item) => "{$item->supplier_id}_{$item->MPN}_{$item->cpn}");

        $rfqBatch = [];

        foreach ($rows as $rowIndex => $row) {
            $mappedRow = [];
            $missingFields = [];
            foreach ($headerRow as $index => $header) {
                $mappedRow[$header] = $row[$index] ?? null;
                if (is_null($mappedRow[$header])) {
                    $missingFields[] = $header;
                }
            }

            if (!empty($missingFields)) {
                $errors[] = [
                    'row' => $rowIndex + 18,
                    'missing_fields' => $missingFields,
                ];
            }

            // Supplier resolve or create
            $supplierName = isset($mappedRow['Supp Name']) ? trim((string)$mappedRow['Supp Name']) : null;

            if ($supplierName === null || $supplierName === '') {
                // Fallback: try supplier via MPN from smtp_details
                $mpnForLookup = $mappedRow['Mfg Part Number'] ?? null;

                if ($mpnForLookup) {
                    // Find a supplier name mapped for this MPN in smtp_details
                    $smtpSupplierName = SmtpDetail::where('mpn', $mpnForLookup)->value('supplier');

                    if ($smtpSupplierName && $smtpSupplierName !== '') {
                        // Try to get supplier id from cache or DB (do NOT create here)
                        $supplierId = $suppliersCache[$smtpSupplierName] ?? Supplier::where('supplier_name', $smtpSupplierName)->value('id');

                        if (!$supplierId) {
                            // No such supplier in suppliers table; skip
                            $errors[] = [
                                'row' => $rowIndex + 18,
                                'error' => 'Missing "Supp Name" and smtp_details supplier not found in suppliers for MPN: ' . $mpnForLookup,
                            ];
                            continue;
                        }

                        // Found existing supplier via smtp_details mapping; update cache for reuse
                        $suppliersCache[$smtpSupplierName] = $supplierId;
                    } else {
                        // No smtp_details entry for this MPN; skip
                        $errors[] = [
                            'row' => $rowIndex + 18,
                            'error' => 'Missing "Supp Name" and no smtp_details mapping for MPN: ' . $mpnForLookup,
                        ];
                        continue;
                    }
                } else {
                    // No supplier name + no MPN to look up => skip
                    $errors[] = [
                        'row' => $rowIndex + 18,
                        'error' => 'Missing "Supp Name" and missing MPN for fallback lookup',
                    ];
                    continue;
                }
            } else {
                // We have a supplier name from the sheet; proceed with original behavior (create if missing)
                $supplierId = $suppliersCache[$supplierName] ?? null;

            if (!$supplierId) {
                $supplier = Supplier::create([
                    'supplier_name'  => $supplierName,
                    'address'        => 'Default Address',
                    'contact_email'  => 'default@example.com',
                    'contact_number' => '0000000000',
                ]);
                $supplierId = $supplier->id;
                $suppliersCache[$supplierName] = $supplierId;
            }
        }


            // FG from BOM
            $FG = optional(optional($bomDataMap[$mappedRow['Part Number']] ?? null)->first())->FG ?? null;

            // Revision No
            $mpn = $mappedRow['Mfg Part Number'] ?? 'No_MPN';
            $cpn = $mappedRow['Part Number'] ?? null;
            $revisionKey = "{$supplierId}_{$mpn}_{$cpn}";
            $revisionNo = isset($rfqRevisions[$revisionKey]) ? $rfqRevisions[$revisionKey]->max_rev + 1 : 0;

            $staticData = [
                'bom_id' => $this->bomId,
                'project_id' => $this->projectId,
                'supplier_id' => $supplierId,
                'MPN' => $mpn,
                'cpn' => $cpn,
                'FG' => $FG,
                'corrected_MPN' => $mappedRow['Corrected MPN'] ?? null,
                'uploaded_through' => 'Third Party',
                'request_uom' => 'N/A',
                'commodity' => $mappedRow['Commodity'] ?? null,
                'no_bid' => $this->sanitizeInteger($mappedRow['NCNR']),
                'ncnr' => $mappedRow['NCNR'] ? "Yes" : null,
                'package_qty' => $this->sanitizeNumeric($mappedRow['Pkg Qty']),
                'part_description' => $mappedRow['Part Description'] ?? 'N/A',
                'vol_moq' => $this->sanitizeInteger($mappedRow['MOQ']),
                'lead_times' => $mappedRow['Lead Time'] ?? null,
                'rfq_comment' => $mappedRow['Long Comment'] ?? null,
                'part_number_id' => null,
                'currency' => $mappedRow['Currency (Original)'] ?? null,
                'effective_from_date' => $this->sanitizeDate($mappedRow['Eff Date']),
                'expiry_date' => $this->sanitizeDate($mappedRow['Exp Date']),
                'quote_validity_weeks' => $this->calculateQuoteValidityWeeks($mappedRow['Eff Date'], $mappedRow['Exp Date']),
                'nre_charge' => null,
                'supplier_type' => $mappedRow['Supplier Type'] ?? '30 Days',
                'price_control' => (empty($mappedRow['Price Type']) || $mappedRow['Price Type'] === 'NO CONTRACT') ? 'Centum' : 'CNP',
                'revision_no' => $revisionNo
            ];

            RFQ::where('bom_id', $this->bomId)
                ->where('cpn', $cpn)
                ->update([
                    'is_cron' => false,
                    'is_L1' => false,
                    'excess_cost' => null,
                    'excess_qty' => null,
                ]);

            foreach ($dynamicHeaders['Price'] as $groupIndex => $priceIndex) {
                $entries = $bomDataMap[$cpn] ?? collect();
                $totalPartQty = $entries
                    ->unique(fn($item) => $item->FG . '||' . ($item->Assembly ?? '') . '||' . $item->CPN)
                    ->pluck('Quantity')
                    ->filter(fn($qty) => is_numeric($qty))
                    ->sum();

                $rfqEntry = $staticData;
                $rfqEntry['total_part_qty'] = $totalPartQty;
                $rfqEntry['unit_price'] = $mappedRow[$headers[$dynamicHeaders['Price'][$groupIndex] ?? null]] ?? null;
                $rfqEntry['vol_pricing'] = $mappedRow[$headers[$dynamicHeaders['Cost'][$groupIndex] ?? null]] ?? null;
                $rfqEntry['vol_qty'] = $mappedRow[$headers[$dynamicHeaders['Awarded Volume'][$groupIndex] ?? null]] ?? null;
                $rfqEntry['volume_no'] = (int)$groupIndex;
                $rfqEntry['is_L1'] = !empty($mappedRow[$headers[$dynamicHeaders['Award'][$groupIndex] ?? null]]);

                if (!$rfqEntry['is_L1']) {
                    continue;
                }

                $rfqBatch[] = $rfqEntry;
            }
        }

        if (!empty($rfqBatch)) {
            try {
                RFQ::insert($rfqBatch);
                } catch (\Exception $e) {
                    Log::error('Error during RFQ batch insert', ['error' => $e->getMessage()]);
                    $errors[] = ['batch_insert_error' => $e->getMessage()];
                }
        }

        MbTabStatus::where('bom_id', $this->bomId)->update(['status' => 'pending']);

        if (!empty($errors)) {
            BomAuditLog::create([
                'user_id' => auth()->id(),
                'folder_name' => 'uploads',
                'file_name' => basename($this->filePath),
                'version' => '1.0',
                'status' => 'completed_with_errors',
                'project_id' => $this->projectId,
                'bom_upload_id' => $this->bomId,
                'errors' => $errors,
            ]);
        }
    }

    private function calculateQuoteValidityWeeks($effDate, $expDate)
    {
        if (!$effDate || !$expDate) return null;

        try {
            return Carbon::parse($effDate)->diffInWeeks(Carbon::parse($expDate));
        } catch (\Exception $e) {
            return null;
        }
    }

    private function sanitizeInteger($value)
    {
        return is_numeric($value) ? (int)$value : null;
    }

    private function sanitizeNumeric($value)
    {
        return is_numeric($value) ? (float)$value : null;
    }

    private function sanitizeDate($date)
    {
        try {
            return Carbon::parse($date)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }
}
