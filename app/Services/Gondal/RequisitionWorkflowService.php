<?php

namespace App\Services\Gondal;

use App\Models\Gondal\ApprovalRule;
use App\Models\Gondal\AuditLog;
use App\Models\Gondal\Requisition;
use App\Models\Gondal\RequisitionEvent;
use App\Models\Gondal\RequisitionItem;
use App\Models\User;
use App\Support\GondalRoleResolver;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RequisitionWorkflowService
{
    public function __construct(
        protected GondalRoleResolver $roleResolver,
    ) {
    }

    public function create(User $requester, array $payload, array $items): Requisition
    {
        return DB::transaction(function () use ($requester, $payload, $items): Requisition {
            $reference = 'REQ-'.str_pad((string) ((int) Requisition::query()->max('id') + 1), 4, '0', STR_PAD_LEFT);

            $requisition = Requisition::query()->create([
                'reference' => $reference,
                'requester_id' => $requester->id,
                'cooperative_id' => $payload['cooperative_id'] ?? null,
                'title' => $payload['title'],
                'description' => $payload['description'] ?? null,
                'total_amount' => (float) $payload['total_amount'],
                'priority' => $payload['priority'] ?? 'medium',
                'status' => 'pending',
                'submitted_at' => now(),
            ]);

            foreach ($items as $item) {
                RequisitionItem::query()->create([
                    'requisition_id' => $requisition->id,
                    'item_name' => (string) ($item['item_name'] ?? 'Item'),
                    'quantity' => (float) ($item['quantity'] ?? 1),
                    'unit' => (string) ($item['unit'] ?? 'unit'),
                    'unit_cost' => (float) ($item['unit_cost'] ?? 0),
                ]);
            }

            $this->appendEvent($requisition, $requester, 'submitted', 'Requisition submitted');

            return $requisition;
        });
    }

    public function approve(Requisition $requisition, User $actor): Requisition
    {
        $this->authorizeTransition($requisition, $actor);

        $requisition->forceFill([
            'status' => 'approved',
            'approved_at' => now(),
            'rejected_at' => null,
        ])->save();

        $this->appendEvent($requisition, $actor, 'approved', 'Requisition approved');

        return $requisition;
    }

    public function reject(Requisition $requisition, User $actor, ?string $notes = null): Requisition
    {
        $this->authorizeTransition($requisition, $actor);

        $requisition->forceFill([
            'status' => 'rejected',
            'rejected_at' => now(),
        ])->save();

        $this->appendEvent($requisition, $actor, 'rejected', $notes ?: 'Requisition rejected');

        return $requisition;
    }

    protected function authorizeTransition(Requisition $requisition, User $actor): void
    {
        if ($requisition->status !== 'pending') {
            throw new HttpException(409, 'Only pending requisitions can transition.');
        }

        if ($this->roleResolver->isAdmin($actor)) {
            return;
        }

        $rule = ApprovalRule::query()
            ->where('is_active', true)
            ->where('approver_role', $this->roleResolver->resolve($actor))
            ->where('min_amount', '<=', $requisition->total_amount)
            ->where('max_amount', '>=', $requisition->total_amount)
            ->first();

        if (! $rule) {
            throw new HttpException(403, 'No matching approval rule for this user and amount.');
        }
    }

    protected function appendEvent(Requisition $requisition, User $actor, string $action, ?string $notes): void
    {
        RequisitionEvent::query()->create([
            'requisition_id' => $requisition->id,
            'actor_id' => $actor->id,
            'action' => $action,
            'notes' => $notes,
        ]);

        AuditLog::query()->create([
            'user_id' => $actor->id,
            'module' => 'requisitions',
            'action' => $action,
            'context' => ['requisition_id' => $requisition->id, 'reference' => $requisition->reference],
        ]);
    }
}
