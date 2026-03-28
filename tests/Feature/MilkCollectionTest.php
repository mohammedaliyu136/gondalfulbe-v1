<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Modules\MilkCollection\Models\MilkCollection;
use App\Models\Vender;
use App\Models\User;

require_once dirname(__DIR__, 2) . '/Modules/MilkCollection/app/Models/MilkCollection.php';

class MilkCollectionTest extends TestCase
{
    // Using simple assertions to verify grading logic without full DB Refresh to avoid slow testing
    // if the existing test environment isn't fully seeded.

    public function test_quality_grade_a_is_assigned()
    {
        $milk = new MilkCollection();
        $milk->quantity = 100;
        $milk->fat_percentage = 4.5;
        $milk->temperature = 18.0;
        
        $milk->assignQualityGrade();

        $this->assertEquals('A', $milk->quality_grade);
        $this->assertNull($milk->rejection_reason);
    }

    public function test_quality_grade_b_is_assigned_on_fat_constraints()
    {
        $milk = new MilkCollection();
        $milk->quantity = 50;
        $milk->fat_percentage = 3.5;
        $milk->temperature = 19.0;
        
        $milk->assignQualityGrade();

        $this->assertEquals('B', $milk->quality_grade);
        $this->assertNull($milk->rejection_reason);
    }

    public function test_quality_grade_b_is_assigned_on_temp_constraints()
    {
        $milk = new MilkCollection();
        $milk->quantity = 50;
        $milk->fat_percentage = 4.5; // Good fat, but temp is B-level
        $milk->temperature = 22.0;
        
        $milk->assignQualityGrade();

        $this->assertEquals('B', $milk->quality_grade);
        $this->assertNull($milk->rejection_reason);
    }

    public function test_quality_grade_c_auto_rejection()
    {
        $milk = new MilkCollection();
        $milk->quantity = 50;
        $milk->fat_percentage = 2.0;
        $milk->temperature = 28.0;
        
        $milk->assignQualityGrade();

        $this->assertEquals('C', $milk->quality_grade);
        $this->assertEquals('Auto-rejected due to quality metrics', $milk->rejection_reason);
    }
}
