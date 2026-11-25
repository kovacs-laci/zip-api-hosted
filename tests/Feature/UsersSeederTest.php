<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\UsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class UsersSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_a_verified_bruno_login_that_can_be_reseeded(): void
    {
        $this->seed();
        $this->seed();

        $user = User::query()->where('email', 'bruno@example.test')->firstOrFail();

        $this->assertDatabaseCount('users', 1);
        $this->assertSame('Bruno Test User', $user->name);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('BrunoTest123!', $user->password));

        $this->postJson('/api/login', [
            'email' => 'bruno@example.test',
            'password' => 'BrunoTest123!',
        ])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonStructure(['token']);
    }

    public function test_users_seeder_refuses_to_run_in_production(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'production');

        $this->expectException(RuntimeException::class);

        (new UsersSeeder)->run();
    }
}
