<?php

namespace App\Policies;

use App\Models\Gondal\AgentProfile;
use App\Models\User;
use App\Services\Gondal\ProgramScopeService;

class AgentProfilePolicy
{
    public function __construct(protected ProgramScopeService $scopeService) {}

    public function viewAny(User $user): bool
    {
        return ! $this->scopeService->isSponsorUser($user) || $this->scopeService->scopedProjectIds($user) !== [];
    }

    public function view(User $user, AgentProfile $agentProfile): bool
    {
        if (! $this->scopeService->isSponsorUser($user)) {
            return true;
        }

        return $this->scopeService
            ->scopedAgentsQuery(AgentProfile::query()->whereKey($agentProfile->id), $user)
            ->exists();
    }
}
