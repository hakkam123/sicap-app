<?php

namespace Tests\Unit\Models;

use App\Models\Consume;
use App\Models\ImportLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_be_created_with_ulid_primary_key(): void
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'name' => 'John Doe',
            'role' => 'user',
        ]);

        $this->assertNotEmpty($user->id);
        $this->assertTrue(Str::isUlid($user->id));
        $this->assertEquals('john@example.com', $user->email);
        $this->assertEquals('user', $user->role);
    }

    public function test_user_password_is_hashed_and_hidden(): void
    {
        $user = User::factory()->create([
            'password' => 'secret123',
        ]);

        $this->assertTrue(Hash::check('secret123', $user->password));
        $this->assertArrayNotHasKey('password', $user->toArray());
        $this->assertArrayNotHasKey('remember_token', $user->toArray());
    }

    public function test_user_admin_factory_state_assigns_admin_role(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertEquals('admin', $admin->role);
        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'role' => 'admin',
        ]);
    }

    public function test_user_has_many_consumes(): void
    {
        $user = User::factory()->create();
        $consume1 = Consume::factory()->create(['created_by' => $user->id]);
        $consume2 = Consume::factory()->create(['created_by' => $user->id]);

        $this->assertCount(2, $user->consumes);
        $this->assertTrue($user->consumes->contains($consume1));
        $this->assertTrue($user->consumes->contains($consume2));
    }

    public function test_user_has_many_import_logs(): void
    {
        $user = User::factory()->create();
        $log1 = ImportLog::factory()->create(['user_id' => $user->id]);
        $log2 = ImportLog::factory()->create(['user_id' => $user->id]);

        $this->assertCount(2, $user->importLogs);
        $this->assertTrue($user->importLogs->contains($log1));
        $this->assertTrue($user->importLogs->contains($log2));
    }

    public function test_user_soft_deletes_and_can_be_restored(): void
    {
        $user = User::factory()->create();
        $userId = $user->id;

        $user->delete();
        $this->assertSoftDeleted('users', ['id' => $userId]);
        $this->assertNull(User::find($userId));
        $this->assertNotNull(User::withTrashed()->find($userId));

        $user->restore();
        $this->assertNotNull(User::find($userId));
        $this->assertNull(User::find($userId)->deleted_at);
    }
}

