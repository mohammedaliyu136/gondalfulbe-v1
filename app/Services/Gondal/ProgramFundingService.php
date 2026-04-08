<?php

namespace App\Services\Gondal;

use App\Models\Gondal\GondalOrder;
use App\Models\Gondal\ProgramFarmerFundingLimit;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ProgramFundingService
{
    public function enforceSponsorFundingLimit(GondalOrder $order): array
    {
        if ($order->payment_mode !== OrderWorkflowService::PAYMENT_MODE_SPONSOR_FUNDED) {
            return [];
        }

        if (! $order->project_id) {
            throw ValidationException::withMessages([
                'project_id' => [__('Sponsor-funded orders require a program project.')],
            ]);
        }

        $project = Project::query()->lockForUpdate()->findOrFail($order->project_id);
        $projectLimitAmount = $project->budget !== null ? (float) $project->budget : null;
        $projectCommittedAmount = $this->committedSponsorFundingForProject($project->id, $order->id);
        $projectRemainingAmount = $projectLimitAmount !== null
            ? round($projectLimitAmount - $projectCommittedAmount, 2)
            : null;

        if ($projectLimitAmount !== null && ($projectCommittedAmount + (float) $order->total_amount) > $projectLimitAmount) {
            throw ValidationException::withMessages([
                'payment_mode' => [__('This sponsor-funded order exceeds the remaining program funding limit.')],
            ]);
        }

        $farmerCommittedAmount = 0.0;
        $farmerLimitAmount = null;
        $farmerRemainingAmount = null;

        if (Schema::hasTable('gondal_program_farmer_funding_limits')) {
            $farmerLimit = ProgramFarmerFundingLimit::query()
                ->lockForUpdate()
                ->where('project_id', $project->id)
                ->where('farmer_id', $order->farmer_id)
                ->where('status', 'active')
                ->first();

            $farmerCommittedAmount = $this->committedSponsorFundingForFarmer($project->id, $order->farmer_id, $order->id);
            $farmerLimitAmount = $farmerLimit?->limit_amount;
            $farmerRemainingAmount = $farmerLimitAmount !== null
                ? round((float) $farmerLimitAmount - $farmerCommittedAmount, 2)
                : null;

            if ($farmerLimitAmount !== null && ($farmerCommittedAmount + (float) $order->total_amount) > (float) $farmerLimitAmount) {
                throw ValidationException::withMessages([
                    'farmer_id' => [__('This sponsor-funded order exceeds the farmer funding limit for the selected program.')],
                ]);
            }
        }

        return [
            'project_limit_amount' => $projectLimitAmount,
            'project_committed_before_order' => $projectCommittedAmount,
            'project_remaining_after_order' => $projectLimitAmount !== null
                ? round($projectRemainingAmount - (float) $order->total_amount, 2)
                : null,
            'farmer_limit_amount' => $farmerLimitAmount,
            'farmer_committed_before_order' => $farmerCommittedAmount,
            'farmer_remaining_after_order' => $farmerLimitAmount !== null
                ? round($farmerRemainingAmount - (float) $order->total_amount, 2)
                : null,
        ];
    }

    public function committedSponsorFundingForProject(int $projectId, ?int $excludeOrderId = null): float
    {
        $query = GondalOrder::query()
            ->where('project_id', $projectId)
            ->where('payment_mode', OrderWorkflowService::PAYMENT_MODE_SPONSOR_FUNDED)
            ->whereIn('status', ['fulfilled', 'submitted'])
            ->whereNull('cancelled_at');

        if ($excludeOrderId !== null) {
            $query->where('id', '!=', $excludeOrderId);
        }

        return round((float) $query->sum(DB::raw('COALESCE(total_amount, 0)')), 2);
    }

    public function committedSponsorFundingForFarmer(int $projectId, int $farmerId, ?int $excludeOrderId = null): float
    {
        $query = GondalOrder::query()
            ->where('project_id', $projectId)
            ->where('farmer_id', $farmerId)
            ->where('payment_mode', OrderWorkflowService::PAYMENT_MODE_SPONSOR_FUNDED)
            ->whereIn('status', ['fulfilled', 'submitted'])
            ->whereNull('cancelled_at');

        if ($excludeOrderId !== null) {
            $query->where('id', '!=', $excludeOrderId);
        }

        return round((float) $query->sum(DB::raw('COALESCE(total_amount, 0)')), 2);
    }
}
