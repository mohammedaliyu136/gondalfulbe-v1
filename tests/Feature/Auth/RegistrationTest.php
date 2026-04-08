<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_registration_screen_can_be_rendered()
    {
        DB::table('settings')->updateOrInsert(['created_by' => 1, 'name' => 'enable_signup'], ['value' => 'on']);
        DB::table('settings')->updateOrInsert(['created_by' => 1, 'name' => 'email_verification'], ['value' => 'off']);
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register()
    {
        DB::table('settings')->updateOrInsert(['created_by' => 1, 'name' => 'enable_signup'], ['value' => 'on']);
        DB::table('settings')->updateOrInsert(['created_by' => 1, 'name' => 'email_verification'], ['value' => 'off']);

        Role::firstOrCreate([
            'name' => 'company',
            'guard_name' => 'web',
        ]);

        $response = $this->post('/register/store', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => 'on',
        ]);

        $user = User::where('email', 'test@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame('company', $user->type);
        $this->assertNotNull($user->email_verified_at);
        $response->assertRedirect(RouteServiceProvider::HOME);
    }
}
