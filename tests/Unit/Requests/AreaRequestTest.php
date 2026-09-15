<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\AreaRequest;
use App\Models\Area;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class AreaRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_area_data_passes_validation(): void
    {
        $request = new AreaRequest();
        $data = [
            'name' => 'Fabrication Area',
            'code' => 'FA',
            'description' => 'Main fabrication line',
        ];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertTrue($validator->passes());
    }

    public function test_missing_name_fails_validation(): void
    {
        $request = new AreaRequest();
        $data = [
            'code' => 'FA',
        ];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
        $this->assertEquals('Nama area wajib diisi.', $validator->errors()->first('name'));
    }

    public function test_missing_code_fails_validation(): void
    {
        $request = new AreaRequest();
        $data = [
            'name' => 'Fabrication Area',
        ];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('code', $validator->errors()->toArray());
        $this->assertEquals('Kode area wajib diisi.', $validator->errors()->first('code'));
    }

    public function test_duplicate_code_fails_validation(): void
    {
        Area::factory()->create(['code' => 'FA']);

        $request = new AreaRequest();
        $data = [
            'name' => 'Another Area',
            'code' => 'FA',
        ];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('code', $validator->errors()->toArray());
        $this->assertEquals('Kode area sudah terdaftar.', $validator->errors()->first('code'));
    }

    public function test_code_exceeding_max_length_fails_validation(): void
    {
        $request = new AreaRequest();
        $data = [
            'name' => 'Test Area',
            'code' => str_repeat('A', 51),
        ];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('code', $validator->errors()->toArray());
        $this->assertEquals('Kode area maksimal 50 karakter.', $validator->errors()->first('code'));
    }

    public function test_area_request_allows_nullable_description(): void
    {
        $request = new AreaRequest();
        $data = [
            'name' => 'SMT Area',
            'code' => 'SMT',
            'description' => null,
        ];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertTrue($validator->passes());
    }
}

