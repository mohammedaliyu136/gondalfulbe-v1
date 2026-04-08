<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use App\Services\Gondal\ProgramScopeService;

class ProjectPolicy
{
    public function __construct(protected ProgramScopeService $scopeService) {}

    public function viewAny(User $user): bool
    {
        return ! $this->scopeService->isSponsorUser($user) || $this->scopeService->scopedProjectIds($user) !== [];
    }

    public function view(User $user, Project $project): bool
    {
        if (! $this->scopeService->isSponsorUser($user)) {
            return true;
        }

        return in_array($project->id, $this->scopeService->scopedProjectIds($user), true);
    }
}
