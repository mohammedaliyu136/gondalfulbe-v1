<?php

namespace App\Services\Gondal;

use App\Models\Gondal\AgentProfile;
use App\Models\Gondal\ProgramAgentAssignment;
use App\Models\Gondal\ProgramFarmerEnrollment;
use App\Models\Project;
use App\Models\User;
use App\Models\Vender;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProgramScopeService
{
    public function isSponsorUser(?User $user): bool
    {
        return (bool) $user && $user->type === 'client';
    }

    public function scopedProjectIds(?User $user): array
    {
        if (! $this->isSponsorUser($user)) {
            return [];
        }

        return Project::query()
            ->where(function (Builder $query) use ($user) {
                $query->where('client_id', $user->id)
                    ->orWhereHas('users', fn (Builder $projectUsers) => $projectUsers->where('users.id', $user->id));
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function scopedProjects(?User $user): Collection
    {
        $projectIds = $this->scopedProjectIds($user);

        if ($projectIds === []) {
            return collect();
        }

        return Project::query()->whereIn('id', $projectIds)->get();
    }

    public function scopedAgentsQuery(Builder $query, ?User $user): Builder
    {
        if (! $this->isSponsorUser($user)) {
            return $query;
        }

        $projectIds = $this->scopedProjectIds($user);

        return $query->where(function (Builder $scopedQuery) use ($projectIds, $user) {
            $scopedQuery->whereIn('project_id', $projectIds)
                ->orWhere('sponsor_user_id', $user->id)
                ->orWhereHas('programAssignments', function (Builder $assignmentQuery) use ($projectIds) {
                    $this->applyActiveProgramWindow(
                        $assignmentQuery->whereIn('project_id', $projectIds)
                    );
                });
        });
    }

    public function scopedFarmerIds(?User $user): array
    {
        if (! $this->isSponsorUser($user)) {
            return [];
        }

        $projectIds = $this->scopedProjectIds($user);
        if ($projectIds === []) {
            return [];
        }

        $explicitEnrollmentQuery = ProgramFarmerEnrollment::query()
            ->whereIn('project_id', $projectIds);
        $explicitEnrollmentIds = $this->applyActiveProgramWindow($explicitEnrollmentQuery)
            ->pluck('farmer_id')
            ->map(fn ($id) => (int) $id);

        $agents = $this->scopedAgentsQuery(
            AgentProfile::query()->with('cooperatives'),
            $user
        )->get();

        $communityIds = $agents->pluck('community_id')->filter()->map(fn ($id) => (int) $id)->values();
        $communityNames = $agents->pluck('community')
            ->filter()
            ->map(fn ($value) => Str::lower(trim((string) $value)))
            ->unique()
            ->values();
        $assignedCommunities = $agents->pluck('assigned_communities')
            ->flatten(1)
            ->filter()
            ->map(fn ($value) => Str::lower(trim((string) $value)))
            ->unique()
            ->values();
        $cooperativeIds = $agents->flatMap(fn (AgentProfile $agent) => $agent->cooperatives->pluck('id'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $query = Vender::query()->select('id');
        $query->where(function (Builder $farmerQuery) use ($explicitEnrollmentIds, $communityIds, $communityNames, $assignedCommunities, $cooperativeIds) {
            if ($explicitEnrollmentIds->isNotEmpty()) {
                $farmerQuery->orWhereIn('id', $explicitEnrollmentIds->all());
            }

            if ($communityIds->isNotEmpty()) {
                $farmerQuery->orWhereIn('community_id', $communityIds->all());
            }

            if ($cooperativeIds->isNotEmpty()) {
                $farmerQuery->orWhereIn('cooperative_id', $cooperativeIds->all());
            }

            $names = $communityNames->merge($assignedCommunities)->unique()->values()->all();
            foreach ($names as $communityName) {
                $farmerQuery->orWhereRaw('LOWER(COALESCE(community, "")) = ?', [$communityName]);
            }
        });

        return $query->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function scopedFarmersQuery(Builder $query, ?User $user): Builder
    {
        if (! $this->isSponsorUser($user)) {
            return $query;
        }

        $farmerIds = $this->scopedFarmerIds($user);

        if ($farmerIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('id', $farmerIds);
    }

    public function scopedMilkCollectionsQuery(Builder $query, ?User $user): Builder
    {
        if (! $this->isSponsorUser($user)) {
            return $query;
        }

        $farmerIds = $this->scopedFarmerIds($user);
        $projectIds = $this->scopedProjectIds($user);

        if ($farmerIds === [] && $projectIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $scopedQuery) use ($farmerIds, $projectIds) {
            if ($projectIds !== []) {
                $scopedQuery->whereIn('project_id', $projectIds);
            }

            if ($farmerIds !== []) {
                $scopedQuery->orWhereIn('farmer_id', $farmerIds);
            }

            if ($projectIds !== []) {
                $scopedQuery->orWhere(function (Builder $legacyQuery) use ($projectIds) {
                    $legacyQuery->whereNull('project_id')
                        ->whereExists(function ($subQuery) use ($projectIds) {
                            $subQuery->select(DB::raw(1))
                                ->from('gondal_program_farmer_enrollments')
                                ->whereColumn('gondal_program_farmer_enrollments.farmer_id', 'milk_collections.farmer_id')
                                ->whereIn('gondal_program_farmer_enrollments.project_id', $projectIds);

                            $this->applyActiveProgramWindow($subQuery);
                        });
                });
            }
        });
    }

    public function scopedOrdersQuery(Builder $query, ?User $user): Builder
    {
        return $this->scopedProjectAndFarmerQuery($query, $user, 'project_id', 'farmer_id');
    }

    public function scopedInventoryCreditsQuery(Builder $query, ?User $user): Builder
    {
        return $this->scopedProjectAndFarmerQuery($query, $user, 'project_id', 'vender_id');
    }

    public function scopedSettlementRunsQuery(Builder $query, ?User $user): Builder
    {
        return $this->scopedProjectAndFarmerQuery($query, $user, 'project_id', 'farmer_id');
    }

    public function scopedPaymentBatchesQuery(Builder $query, ?User $user): Builder
    {
        return $this->scopedProjectAndFarmerQuery($query, $user, 'project_id');
    }

    public function resolveAgentProjectId(?AgentProfile $agentProfile): ?int
    {
        if (! $agentProfile) {
            return null;
        }

        $assignmentQuery = ProgramAgentAssignment::query()
            ->where('agent_profile_id', $agentProfile->id)
            ->orderByDesc('starts_on')
            ->orderByDesc('id');
        $assignmentProjectId = $this->applyActiveProgramWindow($assignmentQuery)
            ->value('project_id');

        if ($assignmentProjectId) {
            return (int) $assignmentProjectId;
        }

        return $agentProfile->project_id ? (int) $agentProfile->project_id : null;
    }

    public function resolveFarmerProjectId(Vender $farmer, ?User $user = null, ?AgentProfile $agentProfile = null): ?int
    {
        $scopedProjectIds = $this->scopedProjectIds($user);
        $enrollmentQuery = $this->applyActiveProgramWindow(
            ProgramFarmerEnrollment::query()
            ->where('farmer_id', $farmer->id)
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
        );

        if ($scopedProjectIds !== []) {
            $scopedEnrollmentProjectId = (clone $enrollmentQuery)
                ->whereIn('project_id', $scopedProjectIds)
                ->value('project_id');

            if ($scopedEnrollmentProjectId) {
                return (int) $scopedEnrollmentProjectId;
            }

            $agentProjectId = $this->resolveAgentProjectId($agentProfile);
            if ($agentProjectId && in_array($agentProjectId, $scopedProjectIds, true)) {
                return $agentProjectId;
            }

            return $scopedProjectIds[0];
        }

        $enrollmentProjectId = (clone $enrollmentQuery)->value('project_id');
        if ($enrollmentProjectId) {
            return (int) $enrollmentProjectId;
        }

        return $this->resolveAgentProjectId($agentProfile);
    }

    public function assignAgentToProject(Project $project, AgentProfile $agentProfile, ?User $actor = null): ProgramAgentAssignment
    {
        return DB::transaction(function () use ($project, $agentProfile, $actor) {
            $today = Carbon::today()->toDateString();

            ProgramAgentAssignment::query()
                ->where('agent_profile_id', $agentProfile->id)
                ->where('project_id', '!=', $project->id)
                ->where('status', 'active')
                ->update([
                    'status' => 'inactive',
                    'ends_on' => $today,
                    'updated_at' => now(),
                ]);

            $assignment = ProgramAgentAssignment::query()->updateOrCreate(
                [
                    'project_id' => $project->id,
                    'agent_profile_id' => $agentProfile->id,
                ],
                [
                    'assigned_by' => $actor?->id,
                    'starts_on' => $today,
                    'ends_on' => null,
                    'status' => 'active',
                ],
            );

            $agentProfile->forceFill([
                'project_id' => $project->id,
            ])->save();

            return $assignment;
        });
    }

    public function enrollFarmerInProject(Project $project, Vender $farmer, ?User $actor = null): ProgramFarmerEnrollment
    {
        return DB::transaction(function () use ($project, $farmer, $actor) {
            $today = Carbon::today()->toDateString();

            ProgramFarmerEnrollment::query()
                ->where('farmer_id', $farmer->id)
                ->where('project_id', '!=', $project->id)
                ->where('status', 'active')
                ->update([
                    'status' => 'inactive',
                    'ends_on' => $today,
                    'updated_at' => now(),
                ]);

            return ProgramFarmerEnrollment::query()->updateOrCreate(
                [
                    'project_id' => $project->id,
                    'farmer_id' => $farmer->id,
                ],
                [
                    'enrolled_by' => $actor?->id,
                    'starts_on' => $today,
                    'ends_on' => null,
                    'status' => 'active',
                ],
            );
        });
    }

    protected function applyActiveProgramWindow(Builder|QueryBuilder $query): Builder|QueryBuilder
    {
        $today = Carbon::today()->toDateString();

        return $query
            ->where('status', 'active')
            ->where(function ($windowQuery) use ($today) {
                $windowQuery->whereNull('starts_on')
                    ->orWhereDate('starts_on', '<=', $today);
            })
            ->where(function ($windowQuery) use ($today) {
                $windowQuery->whereNull('ends_on')
                    ->orWhereDate('ends_on', '>=', $today);
            });
    }

    protected function scopedProjectAndFarmerQuery(
        Builder $query,
        ?User $user,
        string $projectColumn = 'project_id',
        ?string $farmerColumn = null
    ): Builder {
        if (! $this->isSponsorUser($user)) {
            return $query;
        }

        $projectIds = $this->scopedProjectIds($user);
        $farmerIds = $farmerColumn ? $this->scopedFarmerIds($user) : [];

        if ($projectIds === [] && $farmerIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $scopedQuery) use ($projectIds, $farmerIds, $projectColumn, $farmerColumn) {
            if ($projectIds !== []) {
                $scopedQuery->whereIn($projectColumn, $projectIds);
            }

            if ($farmerColumn !== null && $farmerIds !== []) {
                $method = $projectIds !== [] ? 'orWhereIn' : 'whereIn';
                $scopedQuery->{$method}($farmerColumn, $farmerIds);
            }
        });
    }
}
