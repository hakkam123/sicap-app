<?php

namespace App\Services;

use App\Models\ImportLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ImportService
{
    /**
     * Process an uploaded Excel file synchronously with generic ImportLog tracking and guaranteed temp cleanup.
     *
     * @param UploadedFile $file
     * @param string $feature ('consume', 'part_number', 'area', 'machine', 'mapping', etc.)
     * @param object $importer
     * @param string|null $userId
     * @return array
     */
    public function process(UploadedFile $file, string $feature, object $importer, ?string $userId = null): array
    {
        $userId = $userId ?: Auth::id();
        $originalFilename = $file->getClientOriginalName();
        $tempFilename = 'temp_' . Str::ulid() . '.' . ($file->getClientOriginalExtension() ?: 'xlsx');
        
        // Ensure temporary directory exists
        if (!Storage::disk('local')->exists('temp')) {
            Storage::disk('local')->makeDirectory('temp');
        }

        // Store file temporarily
        $tempPath = $file->storeAs('temp', $tempFilename, 'local');
        $fullPath = Storage::disk('local')->path($tempPath);

        // Initial Import Log
        $importLog = ImportLog::create([
            'user_id' => $userId,
            'feature' => $feature,
            'filename' => $originalFilename,
            'status' => 'processing',
            'total_rows' => 0,
            'processed_rows' => 0,
            'success_rows' => 0,
            'failed_rows' => 0,
            'started_at' => now(),
        ]);

        $exception = null;

        try {
            Excel::import($importer, $fullPath);
        } catch (\Throwable $e) {
            $exception = $e;
            Log::error("ImportService exception [{$feature}]: " . $e->getMessage(), [
                'file' => $originalFilename,
                'trace' => $e->getTraceAsString(),
            ]);
        } finally {
            // Guarantee deletion of temporary upload file
            try {
                if (Storage::disk('local')->exists($tempPath)) {
                    Storage::disk('local')->delete($tempPath);
                }
            } catch (\Throwable $ex) {
                Log::warning("Could not delete temporary file {$tempPath}: " . $ex->getMessage());
            }
        }

        if ($exception) {
            $generalError = [
                'row' => '-',
                'field' => 'System',
                'value' => '-',
                'message' => 'Gagal membaca file Excel: ' . $exception->getMessage(),
            ];

            $importLog->update([
                'status' => 'failed',
                'error_message' => 'Gagal memproses file Excel: ' . $exception->getMessage(),
                'error_details' => [$generalError],
                'finished_at' => now(),
            ]);

            return [
                'success' => false,
                'import_log_id' => $importLog->id,
                'status' => 'failed',
                'message' => 'Gagal memproses file Excel: ' . $exception->getMessage(),
                'errors' => [$generalError['message']],
                'error_details' => [$generalError],
                'total_rows' => 0,
                'success_count' => 0,
                'failed_count' => 1,
            ];
        }

        $total = property_exists($importer, 'totalCount') ? $importer->totalCount : 0;
        $successCount = property_exists($importer, 'successCount') ? $importer->successCount : 0;
        $errorCount = property_exists($importer, 'errorCount') ? $importer->errorCount : 0;
        $rawErrors = property_exists($importer, 'errors') ? $importer->errors : [];

        if ($total === 0) {
            $total = $successCount + $errorCount;
        }

        $status = $errorCount > 0 ? 'failed' : 'success';
        $summaryMsg = '';

        if ($status === 'success') {
            $summaryMsg = "Import {$importLog->feature_label} selesai: {$successCount} data berhasil diproses.";
            if (property_exists($importer, 'updatedCount') && $importer->updatedCount > 0) {
                $summaryMsg .= " ({$importer->updatedCount} data diperbarui)";
            }
        } else {
            $summaryMsg = "Import {$importLog->feature_label} selesai dengan catatan: {$successCount} berhasil, {$errorCount} baris bermasalah.";
        }

        // Format string messages for backward compatibility
        $errorStrings = [];
        foreach ($rawErrors as $err) {
            if (is_array($err)) {
                $errorStrings[] = $err['message'] ?? json_encode($err);
            } else {
                $errorStrings[] = (string) $err;
            }
        }

        $importLog->update([
            'status' => $status,
            'total_rows' => $total,
            'processed_rows' => $successCount + $errorCount,
            'success_rows' => $successCount,
            'failed_rows' => $errorCount,
            'error_message' => $errorCount > 0 ? $summaryMsg : null,
            'error_details' => $rawErrors,
            'finished_at' => now(),
        ]);

        return [
            'success' => $status === 'success',
            'import_log_id' => $importLog->id,
            'status' => $status,
            'message' => $summaryMsg,
            'errors' => $errorStrings,
            'error_details' => $rawErrors,
            'total_rows' => $total,
            'success_count' => $successCount,
            'failed_count' => $errorCount,
        ];
    }
}

