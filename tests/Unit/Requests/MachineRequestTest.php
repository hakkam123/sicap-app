<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\MachineRequest;
use App\Models\Area;
use App\Models\Machine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class MachineRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_machine_data_passes_validation(): void
    {
        $area = Area::factory()->create();

        $request = new MachineRequest();
        $data = [
            'area_id' => $area->id,
            'name' => 'SMT Line 1',
            'code' => 'SMT-01',
            'description' => 'High speed mounter',
        ];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertTrue($validator->passes());
    }

    public function test_missing_area_id_fails_validation(): void
    {
        $request = new MachineRequest();
        $data = [
            'name' => 'SMT Line 1',
            'code' => 'SMT-01',
        ];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('area_id', $validator->errors()->toArray());
        $this->assertEquals('Area wajib dipilih.', $validator->errors()->first('area_id'));
    }

    public function test_non_existent_area_id_fails_validation(): void
    {
        $request = new MachineRequest();
        $data = [
            'area_id' => '01JNONEXISTENTAREA000000000',
            'name' => 'SMT Line 1',
            'code' => 'SMT-01',
        ];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('area_id', $validator->errors()->toArray());
        $this->assertEquals('Area yang dipilih tidak valid.', $validator->errors()->first('area_id'));
    }

    public function test_missing_name_and_code_fails_validation(): void
    {
        $area = Area::factory()->create();

        $request = new MachineRequest();
        $data = [
            'area_id' => $area->id,
        ];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
        $this->assertArrayHasKey('code', $validator->errors()->toArray());
    }

    public function test_duplicate_machine_code_fails_validation(): void
    {
        $area = Area::factory()->create();
        Machine::factory()->create(['code' => 'SMT-01']);

        $request = new MachineRequest();
        $data = [
            'area_id' => $area->id,
            'name' => 'Another Machine',
            'code' => 'SMT-01',
        ];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('code', $validator->errors()->toArray());
        $this->assertEquals('Kode mesin sudah terdaftar.', $validator->errors()->first('code'));
    }
}

