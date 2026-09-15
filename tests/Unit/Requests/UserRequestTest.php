<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\UserRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UserRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_user_creation_passes_validation(): void
    {
        $request = new UserRequest();
        $request->setMethod('POST');

        $data = [
            'name' => 'Alice Admin',
            'email' => 'alice@visteon.com',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
            'role' => 'admin',
        ];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertTrue($validator->passes());
    }

    public function test_invalid_email_and_duplicate_email_fails_validation(): void
    {
        User::factory()->create(['email' => 'existing@visteon.com']);

        $request = new UserRequest();
        $request->setMethod('POST');

        $dataDuplicate = [
            'name' => 'Bob',
            'email' => 'existing@visteon.com',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
            'role' => 'user',
        ];

        $validatorDup = Validator::make($dataDuplicate, $request->rules(), $request->messages());
        $this->assertFalse($validatorDup->passes());
        $this->assertArrayHasKey('email', $validatorDup->errors()->toArray());
        $this->assertEquals('Alamat email sudah terdaftar.', $validatorDup->errors()->first('email'));

        $dataInvalid = [
            'name' => 'Bob',
            'email' => 'not-an-email',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
            'role' => 'user',
        ];

        $validatorInv = Validator::make($dataInvalid, $request->rules(), $request->messages());
        $this->assertFalse($validatorInv->passes());
        $this->assertArrayHasKey('email', $validatorInv->errors()->toArray());
        $this->assertEquals('Format alamat email tidak valid.', $validatorInv->errors()->first('email'));
    }

    public function test_short_or_unconfirmed_password_fails_validation_on_create(): void
    {
        $request = new UserRequest();
        $request->setMethod('POST');

        $dataShort = [
            'name' => 'Bob',
            'email' => 'bob@visteon.com',
            'password' => 'short',
            'password_confirmation' => 'short',
            'role' => 'user',
        ];

        $validatorShort = Validator::make($dataShort, $request->rules(), $request->messages());
        $this->assertFalse($validatorShort->passes());
        $this->assertArrayHasKey('password', $validatorShort->errors()->toArray());
        $this->assertNotEmpty($validatorShort->errors()->get('password'));

        $dataMismatch = [
            'name' => 'Bob',
            'email' => 'bob@visteon.com',
            'password' => 'Secret123!',
            'password_confirmation' => 'Different123!',
            'role' => 'user',
        ];

        $validatorMismatch = Validator::make($dataMismatch, $request->rules(), $request->messages());
        $this->assertFalse($validatorMismatch->passes());
        $this->assertArrayHasKey('password', $validatorMismatch->errors()->toArray());
        $this->assertEquals('Konfirmasi kata sandi tidak cocok.', $validatorMismatch->errors()->first('password'));
    }

    public function test_password_is_optional_on_user_update(): void
    {
        $user = User::factory()->create(['email' => 'update@visteon.com']);

        $request = UserRequest::create('/users/' . $user->id, 'PUT');
        $route = new \Illuminate\Routing\Route('PUT', '/users/{user}', []);
        $route->bind($request);
        $route->setParameter('user', $user->id);
        $request->setRouteResolver(fn () => $route);

        $data = [
            'name' => 'Updated Name',
            'email' => 'update@visteon.com',
            'password' => null,
            'role' => 'user',
        ];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertTrue($validator->passes());
    }

    public function test_invalid_role_fails_validation(): void
    {
        $request = new UserRequest();
        $request->setMethod('POST');

        $data = [
            'name' => 'Hacker',
            'email' => 'hacker@visteon.com',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
            'role' => 'superadmin_invalid',
        ];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('role', $validator->errors()->toArray());
        $this->assertEquals('Peran (role) harus berupa admin atau user.', $validator->errors()->first('role'));
    }
}
