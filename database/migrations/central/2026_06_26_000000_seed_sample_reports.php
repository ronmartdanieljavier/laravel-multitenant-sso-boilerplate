<?php

use App\Reports\Jobs\GenerateReportJob;
use Illuminate\Bus\Batch;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $adminId = DB::table('users')
            ->where('email', env('ADMIN_EMAIL', 'admin@example.com'))
            ->value('id');

        $demoTenant = DB::table('tenants')->where('slug', 'demo')->first();

        if (! $adminId || ! $demoTenant) {
            return;
        }

        $now = now();

        // Two individual reports dispatched separately
        $screenReportId = (string) Str::uuid();
        $pdfReportId = (string) Str::uuid();

        DB::table('reports')->insert([
            [
                'id' => $screenReportId,
                'user_id' => $adminId,
                'tenant_id' => $demoTenant->id,
                'type' => 'user_activity',
                'format' => 'screen',
                'delivery' => 'download',
                'status' => 'pending',
                'parameters' => null,
                'file_path' => null,
                'error_message' => null,
                'batch_id' => null,
                'started_at' => null,
                'completed_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => $pdfReportId,
                'user_id' => $adminId,
                'tenant_id' => $demoTenant->id,
                'type' => 'audit_log',
                'format' => 'pdf',
                'delivery' => 'download',
                'status' => 'pending',
                'parameters' => null,
                'file_path' => null,
                'error_message' => null,
                'batch_id' => null,
                'started_at' => null,
                'completed_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        GenerateReportJob::dispatch($screenReportId);
        GenerateReportJob::dispatch($pdfReportId);

        // Two batch reports dispatched together
        $batchGroupId = (string) Str::uuid();
        $batchReport1Id = (string) Str::uuid();
        $batchReport2Id = (string) Str::uuid();

        DB::table('reports')->insert([
            [
                'id' => $batchReport1Id,
                'user_id' => $adminId,
                'tenant_id' => $demoTenant->id,
                'type' => 'app_access',
                'format' => 'screen',
                'delivery' => 'download',
                'status' => 'pending',
                'parameters' => null,
                'file_path' => null,
                'error_message' => null,
                'batch_id' => $batchGroupId,
                'started_at' => null,
                'completed_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => $batchReport2Id,
                'user_id' => $adminId,
                'tenant_id' => $demoTenant->id,
                'type' => 'user_activity',
                'format' => 'excel',
                'delivery' => 'download',
                'status' => 'pending',
                'parameters' => null,
                'file_path' => null,
                'error_message' => null,
                'batch_id' => $batchGroupId,
                'started_at' => null,
                'completed_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        Bus::batch([
            new GenerateReportJob($batchReport1Id),
            new GenerateReportJob($batchReport2Id),
        ])->dispatch();
    }

    public function down(): void
    {
        // No-op: seeded reports are intentional demo data and should not be auto-deleted on rollback.
    }
};
