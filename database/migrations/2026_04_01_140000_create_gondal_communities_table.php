<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gondal_communities')) {
            Schema::create('gondal_communities', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('state')->nullable();
                $table->string('lga')->nullable();
                $table->string('code')->unique();
                $table->string('status')->default('active');
                $table->timestamps();
                $table->unique(['name', 'state', 'lga']);
            });
        }

        if (Schema::hasTable('venders') && ! Schema::hasColumn('venders', 'community_id')) {
            Schema::table('venders', function (Blueprint $table) {
                $table->foreignId('community_id')->nullable()->after('cooperative_id')->constrained('gondal_communities')->nullOnDelete();
            });
        }

        if (Schema::hasTable('gondal_agent_profiles') && ! Schema::hasColumn('gondal_agent_profiles', 'community_id')) {
            Schema::table('gondal_agent_profiles', function (Blueprint $table) {
                $table->foreignId('community_id')->nullable()->after('lga')->constrained('gondal_communities')->nullOnDelete();
            });
        }

        $seededIds = [];

        if (Schema::hasTable('venders')) {
            $farmers = DB::table('venders')
                ->select('id', 'community', 'state', 'lga')
                ->whereNotNull('community')
                ->where('community', '!=', '')
                ->get();

            foreach ($farmers as $farmer) {
                $communityId = $this->firstOrCreateCommunityId((string) $farmer->community, $farmer->state, $farmer->lga);
                $seededIds[] = $communityId;
                DB::table('venders')->where('id', $farmer->id)->update(['community_id' => $communityId]);
            }
        }

        if (Schema::hasTable('gondal_agent_profiles')) {
            $agents = DB::table('gondal_agent_profiles')
                ->select('id', 'community', 'state', 'lga')
                ->whereNotNull('community')
                ->where('community', '!=', '')
                ->get();

            foreach ($agents as $agent) {
                $communityId = $this->firstOrCreateCommunityId((string) $agent->community, $agent->state, $agent->lga);
                $seededIds[] = $communityId;
                DB::table('gondal_agent_profiles')->where('id', $agent->id)->update(['community_id' => $communityId]);
            }
        }

        $seededIds = array_values(array_unique(array_filter($seededIds)));

        if (Schema::hasTable('gondal_agent_profiles') && Schema::hasColumn('gondal_agent_profiles', 'assigned_communities')) {
            $agents = DB::table('gondal_agent_profiles')
                ->select('id', 'assigned_communities', 'state', 'lga')
                ->whereNotNull('assigned_communities')
                ->get();

            foreach ($agents as $agent) {
                $communities = json_decode((string) $agent->assigned_communities, true);
                if (! is_array($communities)) {
                    continue;
                }

                foreach ($communities as $communityName) {
                    $seededIds[] = $this->firstOrCreateCommunityId((string) $communityName, $agent->state, $agent->lga);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('gondal_agent_profiles') && Schema::hasColumn('gondal_agent_profiles', 'community_id')) {
            Schema::table('gondal_agent_profiles', function (Blueprint $table) {
                $table->dropConstrainedForeignId('community_id');
            });
        }

        if (Schema::hasTable('venders') && Schema::hasColumn('venders', 'community_id')) {
            Schema::table('venders', function (Blueprint $table) {
                $table->dropConstrainedForeignId('community_id');
            });
        }

        Schema::dropIfExists('gondal_communities');
    }

    protected function firstOrCreateCommunityId(string $name, ?string $state, ?string $lga): int
    {
        $name = trim($name);
        $state = $state !== null ? trim($state) : null;
        $lga = $lga !== null ? trim($lga) : null;

        $existing = DB::table('gondal_communities')
            ->where('name', $name)
            ->where('state', $state !== '' ? $state : null)
            ->where('lga', $lga !== '' ? $lga : null)
            ->first();

        if ($existing) {
            return (int) $existing->id;
        }

        $base = strtoupper(Str::slug($name !== '' ? $name : 'community', '-'));
        $base = $base !== '' ? $base : 'COMMUNITY';
        $code = 'COM-'.$base;
        $suffix = 2;

        while (DB::table('gondal_communities')->where('code', $code)->exists()) {
            $code = 'COM-'.$base.'-'.$suffix;
            $suffix++;
        }

        return (int) DB::table('gondal_communities')->insertGetId([
            'name' => $name,
            'state' => $state !== '' ? $state : null,
            'lga' => $lga !== '' ? $lga : null,
            'code' => $code,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
