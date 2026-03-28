<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class CooperativeTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_cooperative_creation()
    {
        $coop = new \Modules\Cooperatives\Models\Cooperative();
        $coop->name = 'Test Cooperative ' . rand(1, 1000);
        $coop->location = 'Test Location';
        $coop->save();

        $this->assertDatabaseHas('cooperatives', [
            'name' => $coop->name,
        ]);
    }
}
