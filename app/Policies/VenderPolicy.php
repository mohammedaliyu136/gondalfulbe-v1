<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vender;
use App\Services\Gondal\ProgramScopeService;

class VenderPolicy
{
    public function __construct(protected ProgramScopeService $scopeService) {}

    public function viewAny(User $user): bool
    {
        return ! $this->scopeService->isSponsorUser($user) || $this->scopeService->scopedFarmerIds($user) !== [];
    }

    public function view(User $user, Vender $vender): bool
    {
        if (! $this->scopeService->isSponsorUser($user)) {
            return true;
        }

        return in_array($vender->id, $this->scopeService->scopedFarmerIds($user), true);
    }
}
