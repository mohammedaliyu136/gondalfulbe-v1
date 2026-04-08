<?php

namespace App\Providers;

use App\Models\Gondal\AgentProfile;
use App\Models\Project;
use App\Models\Vender;
use App\Policies\AgentProfilePolicy;
use App\Policies\MilkCollectionPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\VenderPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Modules\MilkCollection\Models\MilkCollection;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Project::class => ProjectPolicy::class,
        AgentProfile::class => AgentProfilePolicy::class,
        Vender::class => VenderPolicy::class,
        MilkCollection::class => MilkCollectionPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        //
    }
}
