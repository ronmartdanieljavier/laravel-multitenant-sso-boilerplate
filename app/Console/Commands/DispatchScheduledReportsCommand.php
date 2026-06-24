<?php

namespace App\Console\Commands;

use App\Data\Repositories\Central\TenantRepositoryData;
use App\Models\Tenant\ReportSubscription;
use App\Reports\Enums\ReportStatus;
use App\Reports\Jobs\GenerateReportJob;
use App\Repositories\Central\ReportRepository;
use App\Repositories\Central\TenantRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class DispatchScheduledReportsCommand extends Command
{
    protected $signature = 'reports:dispatch-subscriptions
                            {--tenant= : Only dispatch for a specific tenant slug}';

    protected $description = 'Dispatch report generation jobs for due subscription schedules across all tenants';

    public function __construct(
        private TenantRepository $tenantRepository,
        private ReportRepository $reportRepository,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $tenants = $this->tenantRepository->listActive($this->option('tenant') ?: null);

        if ($tenants->isEmpty()) {
            $this->info('No active tenants found.');

            return self::SUCCESS;
        }

        $dispatched = 0;

        foreach ($tenants as $tenant) {
            /** @var TenantRepositoryData $tenant */
            try {
                $this->configureTenantConnection($tenant);

                $count = $this->dispatchForTenant($tenant);
                $dispatched += $count;

                if ($count > 0) {
                    $this->line("  <fg=green>✓</> {$tenant->name}: dispatched {$count} report(s)");
                }
            } catch (\Throwable $e) {
                $this->error("  ✗ {$tenant->name}: {$e->getMessage()}");
            } finally {
                DB::purge('tenant');
            }
        }

        $this->info("Done — {$dispatched} report job(s) dispatched.");

        return self::SUCCESS;
    }

    private function configureTenantConnection(TenantRepositoryData $tenant): void
    {
        Config::set('database.connections.tenant', [
            'driver' => 'pgsql',
            'host' => $tenant->dbHost,
            'port' => $tenant->dbPort,
            'database' => $tenant->dbName,
            'username' => $tenant->dbUsername,
            'password' => $tenant->dbPassword,
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
            'sslmode' => 'prefer',
        ]);

        DB::purge('tenant');
        DB::reconnect('tenant');
    }

    private function dispatchForTenant(TenantRepositoryData $tenant): int
    {
        $subscriptions = ReportSubscription::on('tenant')
            ->where('is_active', true)
            ->get()
            ->filter(fn (ReportSubscription $s) => $s->isDue());

        foreach ($subscriptions as $subscription) {
            $dto = $this->reportRepository->create([
                'tenant_id' => $tenant->id,
                'type' => $subscription->type,
                'format' => $subscription->format,
                'delivery' => $subscription->delivery,
                'status' => ReportStatus::Pending,
                'parameters' => [
                    'subscription_id' => $subscription->id,
                    'recipients' => $subscription->recipients,
                    's3_path' => $subscription->s3_path,
                ],
            ]);

            GenerateReportJob::dispatch($dto->id);

            $subscription->update(['last_dispatched_at' => now()]);
        }

        return $subscriptions->count();
    }
}
