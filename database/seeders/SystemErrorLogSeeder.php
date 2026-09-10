<?php

namespace Database\Seeders;

use App\Models\SystemErrorLog;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SystemErrorLogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (SystemErrorLog::count() > 0) {
            return;
        }

        $admin = User::where('role', 'admin')->first();

        $sampleLogs = [
            [
                'id' => (string) Str::ulid(),
                'error_type' => 'HTTP 500 Internal Server Error',
                'status_code' => 500,
                'severity' => 'critical',
                'feature' => 'sync_api',
                'message' => 'Connection to external SAP/BAAN server timed out after 60 seconds (HTTP 504 Gateway Timeout).',
                'exception_class' => 'Illuminate\Http\Client\ConnectionException',
                'file' => 'app/Services/ConsumeSyncService.php',
                'line' => 62,
                'url' => 'http://127.0.0.1:8000/consume/sync-api',
                'method' => 'POST',
                'request_payload' => json_encode([
                    'endpoint' => 'https://baan-gateway.visteon.internal/api/consumptions',
                    'timeout' => 60,
                    'triggered_by' => 'scheduler_cron',
                ], JSON_PRETTY_PRINT),
                'stack_trace' => "#0 app/Services/ConsumeSyncService.php(62): Illuminate\\Http\\Client\\PendingRequest->get('https://baan-ga...')\n#1 app/Http/Controllers/ConsumeController.php(214): App\\Services\\ConsumeSyncService->sync(NULL, '01J6G7...')\n#2 vendor/laravel/framework/src/Illuminate/Routing/ControllerDispatcher.php(46): App\\Http\\Controllers\\ConsumeController->syncApi(Object(App\\Services\\ConsumeSyncService))",
                'user_id' => $admin?->id,
                'user_ip' => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/128.0.0.0',
                'status' => 'unresolved',
                'created_at' => now()->subMinutes(15),
                'updated_at' => now()->subMinutes(15),
            ],
            [
                'id' => (string) Str::ulid(),
                'error_type' => 'HTTP 404 Not Found',
                'status_code' => 404,
                'severity' => 'warning',
                'feature' => 'consume',
                'message' => 'No query results for model [App\Models\Consume] 01J7K8M9N0PQR.',
                'exception_class' => 'Illuminate\Database\Eloquent\ModelNotFoundException',
                'file' => 'vendor/laravel/framework/src/Illuminate/Database/Eloquent/Builder.php',
                'line' => 610,
                'url' => 'http://127.0.0.1:8000/consume/01J7K8M9N0PQR/edit',
                'method' => 'GET',
                'request_payload' => json_encode(['route_param' => '01J7K8M9N0PQR'], JSON_PRETTY_PRINT),
                'stack_trace' => "#0 vendor/laravel/framework/src/Illuminate/Database/Eloquent/Builder.php(610): Illuminate\\Database\\Eloquent\\Builder->firstOrFail()\n#1 vendor/laravel/framework/src/Illuminate/Routing/ImplicitRouteBinding.php(57): App\\Models\\Consume::resolveRouteBinding('01J7K8M9N0PQR')",
                'user_id' => $admin?->id,
                'user_ip' => '192.168.1.45',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/128.0.0.0',
                'status' => 'unresolved',
                'created_at' => now()->subHours(1),
                'updated_at' => now()->subHours(1),
            ],
            [
                'id' => (string) Str::ulid(),
                'error_type' => 'Database Query Exception',
                'status_code' => 500,
                'severity' => 'critical',
                'feature' => 'mapping',
                'message' => 'SQLSTATE[23000]: Integrity constraint violation: 2601 Cannot insert duplicate key row in object \'dbo.machine_part_number\'.',
                'exception_class' => 'Illuminate\Database\QueryException',
                'file' => 'app/Http/Controllers/MappingController.php',
                'line' => 142,
                'url' => 'http://127.0.0.1:8000/mapping/sync',
                'method' => 'POST',
                'request_payload' => json_encode([
                    'part_number_id' => '01J6G7...',
                    'assigned_machines' => ['01J6G7...', '01J6G7...'],
                ], JSON_PRETTY_PRINT),
                'stack_trace' => "#0 vendor/laravel/framework/src/Illuminate/Database/Connection.php(601): PDOStatement->execute()\n#1 app/Http/Controllers/MappingController.php(142): Illuminate\\Database\\Eloquent\\Relations\\BelongsToMany->sync(Array)",
                'user_id' => $admin?->id,
                'user_ip' => '192.168.1.12',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/128.0.0.0',
                'status' => 'resolved',
                'resolved_at' => now()->subMinutes(30),
                'resolved_by' => $admin?->id,
                'resolution_notes' => 'Telah diperbaiki logika sinkronisasi duplicate mapping key.',
                'created_at' => now()->subHours(3),
                'updated_at' => now()->subMinutes(30),
            ],
            [
                'id' => (string) Str::ulid(),
                'error_type' => 'HTTP 422 Unprocessable Content',
                'status_code' => 422,
                'severity' => 'info',
                'feature' => 'part_number',
                'message' => 'The pn_baan has already been taken.',
                'exception_class' => 'Illuminate\Validation\ValidationException',
                'file' => 'app/Http/Requests/PartNumberRequest.php',
                'line' => 28,
                'url' => 'http://127.0.0.1:8000/part-numbers',
                'method' => 'POST',
                'request_payload' => json_encode([
                    'pn_baan' => 'SPFAMEBEASKF-1281',
                    'description' => 'Bearing SKF 6001-2Z/C3',
                ], JSON_PRETTY_PRINT),
                'stack_trace' => "#0 app/Http/Requests/PartNumberRequest.php(28): Illuminate\\Validation\\Validator->validate()\n#1 app/Http/Controllers/PartNumberController.php(45): App\\Http\\Requests\\PartNumberRequest->validated()",
                'user_id' => $admin?->id,
                'user_ip' => '192.168.1.88',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/128.0.0.0',
                'status' => 'resolved',
                'resolved_at' => now()->subHours(2),
                'resolved_by' => $admin?->id,
                'resolution_notes' => 'User mencoba input nomor part duplikat.',
                'created_at' => now()->subHours(4),
                'updated_at' => now()->subHours(2),
            ],
        ];

        foreach ($sampleLogs as $log) {
            SystemErrorLog::create($log);
        }
    }
}
