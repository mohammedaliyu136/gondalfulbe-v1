<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\User;
use App\Models\Gondal\AgentProfile;
use App\Models\Gondal\AgentRemittance;
use App\Models\Gondal\Community;
use App\Models\Gondal\ExtensionVisit;
use App\Models\Gondal\InventoryCredit;
use App\Models\Gondal\InventoryReconciliation;
use App\Models\Gondal\InventorySale;
use App\Models\Gondal\OneStopShop;
use App\Models\Gondal\StockIssue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportSeboreYolaAgentsSeeder extends Seeder
{
    public function run(): void
    {
        $file = base_path('VBHCD_ToT_Attendance_Sebore_CAEs - Yola Center.xlsx');

        if (! file_exists($file)) {
            throw new \RuntimeException('Workbook not found: '.$file);
        }

        $creatorId = (int) (
            Project::query()->value('created_by')
            ?? User::query()->where('type', 'company')->value('id')
            ?? User::query()->value('id')
            ?? 1
        );

        DB::transaction(function () use ($file, $creatorId): void {
            $project = $this->resolveProject($creatorId);
            $oneStopShop = $this->resolveOneStopShop($creatorId);

            $this->clearExistingAgents();

            $rows = $this->workbookRows($file);
            $imported = 0;

            foreach ($rows as $index => $row) {
                $serial = $row['serial'];
                $fullName = trim((string) $row['name']);

                if ($fullName === '') {
                    continue;
                }

                [$firstName, $middleName, $lastName] = $this->splitName($fullName);
                $community = $this->resolveCommunity($row['community'], $row['state'], $row['lga']);
                $email = $this->uniqueImportEmail($fullName, $serial);

                AgentProfile::query()->create([
                    'user_id' => null,
                    'vender_id' => null,
                    'supervisor_user_id' => null,
                    'sponsor_user_id' => null,
                    'project_id' => $project->id,
                    'one_stop_shop_id' => $oneStopShop->id,
                    'agent_code' => sprintf('AGT-IMP-%03d', $index + 1),
                    'agent_type' => 'independent_reseller',
                    'first_name' => $firstName,
                    'middle_name' => $middleName,
                    'last_name' => $lastName,
                    'gender' => $this->normalizeGender($row['gender']),
                    'phone_number' => $this->normalizePhone($row['phone']),
                    'email' => $email,
                    'nin' => null,
                    'state' => $row['state'],
                    'lga' => $row['lga'],
                    'community_id' => $community->id,
                    'community' => $community->name,
                    'residential_address' => $row['address'] !== '' ? $row['address'] : $community->name,
                    'permanent_address' => $row['address'] !== '' ? $row['address'] : $community->name,
                    'account_number' => null,
                    'account_name' => null,
                    'bank_details' => null,
                    'assigned_communities' => [$community->name],
                    'assigned_warehouse' => $oneStopShop->name,
                    'reconciliation_frequency' => 'weekly',
                    'settlement_mode' => 'consignment',
                    'credit_sales_enabled' => true,
                    'credit_limit' => 0,
                    'stock_variance_tolerance' => 0,
                    'cash_variance_tolerance' => 0,
                    'status' => 'active',
                    'notes' => 'Imported from VBHCD ToT Attendance workbook (Yola Center). Age: '.($row['age'] !== '' ? $row['age'] : 'N/A'),
                ]);

                $imported++;
            }

            $this->command?->info('Imported '.$imported.' agents from workbook.');
            $this->command?->info('Project: '.$project->project_name);
            $this->command?->info('One-Stop Shop: '.$oneStopShop->name);
        });
    }

    protected function clearExistingAgents(): void
    {
        ExtensionVisit::query()->whereNotNull('agent_profile_id')->delete();
        InventoryCredit::query()->whereNotNull('agent_profile_id')->delete();
        InventorySale::query()->whereNotNull('agent_profile_id')->delete();
        AgentRemittance::query()->delete();
        InventoryReconciliation::query()->delete();
        StockIssue::query()->whereNotNull('agent_profile_id')->delete();
        DB::table('gondal_agent_inventory_adjustments')->delete();
        DB::table('gondal_agent_cash_liabilities')->delete();
        DB::table('gondal_agent_profile_cooperative')->delete();
        AgentProfile::query()->delete();
    }

    protected function workbookRows(string $file): array
    {
        $spreadsheet = IOFactory::load($file);
        $sheet = $spreadsheet->getSheetByName('Attendance') ?: $spreadsheet->getActiveSheet();
        $rows = [];

        foreach ($sheet->toArray(null, true, true, false) as $row) {
            $serial = trim((string) ($row[0] ?? ''));
            $name = trim((string) ($row[1] ?? ''));
            $address = trim((string) ($row[2] ?? ''));
            $community = trim((string) ($row[3] ?? ''));
            $lga = trim((string) ($row[4] ?? ''));
            $state = trim((string) ($row[5] ?? ''));
            $gender = trim((string) ($row[6] ?? ''));
            $phone = trim((string) ($row[7] ?? ''));
            $age = trim((string) ($row[8] ?? ''));

            if (! is_numeric($serial) || $name === '') {
                continue;
            }

            $rows[] = [
                'serial' => (int) $serial,
                'name' => $name,
                'address' => $address,
                'community' => $community !== '' ? $community : $lga,
                'lga' => $lga,
                'state' => $state,
                'gender' => $gender,
                'phone' => $phone,
                'age' => $age,
            ];
        }

        return $rows;
    }

    protected function resolveProject(int $creatorId): Project
    {
        return Project::query()->firstOrCreate(
            [
                'project_name' => 'VBHCD ToT - Sebore CAEs - Yola Center',
                'created_by' => $creatorId,
            ],
            [
                'start_date' => now()->toDateString(),
                'end_date' => null,
                'project_image' => null,
                'budget' => null,
                'client_id' => 0,
                'description' => 'Imported CAE cohort from the VBHCD ToT Attendance workbook for Yola Center.',
                'status' => 'in_progress',
                'estimated_hrs' => null,
                'copylinksetting' => '{"member":"on","milestone":"off","basic_details":"on","activity":"off","attachment":"on","bug_report":"on","task":"off","tracker_details":"off","timesheet":"off","password_protected":"off"}',
                'tags' => 'VBHCD,Yola Center,CAE,Imported',
            ]
        );
    }

    protected function resolveOneStopShop(int $creatorId): OneStopShop
    {
        return OneStopShop::query()->firstOrCreate(
            [
                'name' => 'Yola Center OSS',
                'created_by' => $creatorId,
            ],
            [
                'code' => 'OSS-YOLA-CENTER',
                'warehouse_id' => null,
                'state' => 'Adamawa',
                'lga' => 'Yola South',
                'community_id' => null,
                'address' => 'Yola Center',
                'status' => 'active',
            ]
        );
    }

    protected function resolveCommunity(string $name, string $state, string $lga): Community
    {
        $name = $this->normalizeValue($name);
        $state = $this->normalizeValue($state);
        $lga = $this->normalizeValue($lga);

        $existing = Community::query()
            ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
            ->whereRaw('LOWER(COALESCE(state, "")) = ?', [Str::lower($state)])
            ->whereRaw('LOWER(COALESCE(lga, "")) = ?', [Str::lower($lga)])
            ->first();

        if ($existing) {
            return $existing;
        }

        $base = strtoupper(Str::slug($name !== '' ? $name : 'community', '-'));
        $base = $base !== '' ? $base : 'COMMUNITY';
        $code = 'COM-'.$base;
        $suffix = 2;

        while (Community::query()->where('code', $code)->exists()) {
            $code = 'COM-'.$base.'-'.$suffix;
            $suffix++;
        }

        return Community::query()->create([
            'name' => $name,
            'state' => $state !== '' ? $state : null,
            'lga' => $lga !== '' ? $lga : null,
            'code' => $code,
            'status' => 'active',
        ]);
    }

    protected function splitName(string $fullName): array
    {
        $parts = collect(preg_split('/\s+/', trim($fullName)) ?: [])->filter()->values();

        if ($parts->count() === 0) {
            return ['Unknown', null, 'Agent'];
        }

        if ($parts->count() === 1) {
            return [$parts[0], null, $parts[0]];
        }

        return [
            (string) $parts->first(),
            $parts->count() > 2 ? $parts->slice(1, -1)->implode(' ') : null,
            (string) $parts->last(),
        ];
    }

    protected function normalizeGender(string $value): string
    {
        return match (Str::lower(trim($value))) {
            'm', 'male' => 'male',
            'f', 'female' => 'female',
            default => 'other',
        };
    }

    protected function normalizePhone(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?: '';

        return $digits !== '' ? $digits : '00000000000';
    }

    protected function uniqueImportEmail(string $fullName, int $serial): string
    {
        $base = Str::slug($fullName, '.');
        $base = $base !== '' ? $base : 'agent';

        return $base.'.'.$serial.'@agents.import.local';
    }

    protected function normalizeValue(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', $value) ?: '');
    }
}
