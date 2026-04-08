<?php

namespace App\Policies;

use App\Models\User;
use App\Services\Gondal\ProgramScopeService;
use Modules\MilkCollection\Models\MilkCollection;

class MilkCollectionPolicy
{
    public function __construct(protected ProgramScopeService $scopeService) {}

    public function viewAny(User $user): bool
    {
        return ! $this->scopeService->isSponsorUser($user)
            || $this->scopeService->scopedFarmerIds($user) !== []
            || $this->scopeService->scopedProjectIds($user) !== [];
    }

    public function view(User $user, MilkCollection $milkCollection): bool
    {
        if (! $this->scopeService->isSponsorUser($user)) {
            return true;
        }

        return $this->scopeService
            ->scopedMilkCollectionsQuery(MilkCollection::query()->whereKey($milkCollection->id), $user)
            ->exists();
    }
}
