<?php

namespace App\Services\Gondal;

use App\Models\Gondal\OperationCost;
use App\Models\Gondal\Requisition;
use Modules\MilkCollection\Models\MilkCollection;

class ReportService
{
    public function summary(array $filters = []): array
    {
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        $status = $filters['status'] ?? null;

        $collections = MilkCollection::query();
        $costs = OperationCost::query();
        $requisitions = Requisition::query();

        if ($from) {
            $collections->whereDate('collection_date', '>=', $from);
            $costs->whereDate('cost_date', '>=', $from);
            $requisitions->whereDate('submitted_at', '>=', $from);
        }

        if ($to) {
            $collections->whereDate('collection_date', '<=', $to);
            $costs->whereDate('cost_date', '<=', $to);
            $requisitions->whereDate('submitted_at', '<=', $to);
        }

        if ($status) {
            $requisitions->where('status', $status);
        }

        $totalCollection = (float) $collections->sum('quantity');
        $totalCost = (float) $costs->sum('amount');
        $requisitionCount = (float) $requisitions->count();

        return [
            'total_collection' => $totalCollection,
            'total_cost' => $totalCost,
            'net_value' => $totalCollection - $totalCost,
            'requisition_count' => $requisitionCount,
        ];
    }

    public function exportRows(array $filters = []): array
    {
        $summary = $this->summary($filters);

        return [
            ['metric' => 'total_collection', 'value' => (string) $summary['total_collection']],
            ['metric' => 'total_cost', 'value' => (string) $summary['total_cost']],
            ['metric' => 'net_value', 'value' => (string) $summary['net_value']],
            ['metric' => 'requisition_count', 'value' => (string) $summary['requisition_count']],
        ];
    }
}
