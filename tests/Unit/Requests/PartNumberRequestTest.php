<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\PartNumberRequest;
use App\Models\Area;
use App\Models\Machine;
use App\Models\PartNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class PartNumberRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_part_number_data_passes_validation(): void
    {
        $area = Area::factory()->create();
        $machine = Machine::factory()->create(['area_id' => $area->id]);

        $request = new PartNumberRequest();
        $data = [
            'pn_baan' => 'PN-IC-74HC595',
            'description' => '8-bit Shift Register',
            'area_ids' => [$area->id],
            'machine_ids' => [$machine->id],
        ];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertTrue($validator->passes());
    }

    public function test_missing_pn_baan_fails_validation(): void
    {
        $request = new PartNumberRequest();
        $data = [
            'description' => 'Some description',
        ];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('pn_baan', $validator->errors()->toArray());
        $this->assertEquals('Nomor part (PN BAAN) wajib diisi.', $validator->errors()->first('pn_baan'));
    }

    public function test_duplicate_pn_baan_fails_validation(): void
    {
        PartNumber::factory()->create(['pn_baan' => 'PN-IC-74HC595']);

        $request = new PartNumberRequest();
        $data = [
            'pn_baan' => 'PN-IC-74HC595',
            'description' => 'Another part with same PN',
        ];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('pn_baan', $validator->errors()->toArray());
        $this->assertEquals('Nomor part (PN BAAN) sudah terdaftar.', $validator->errors()->first('pn_baan'));
    }

    public function test_pn_baan_exceeding_max_length_fails_validation(): void
    {
        $request = new PartNumberRequest();
        $data = [
            'pn_baan' => str_repeat('X', 101),
        ];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('pn_baan', $validator->errors()->toArray());
        $this->assertEquals('Nomor part maksimal 100 karakter.', $validator->errors()->first('pn_baan'));
    }

    public function test_invalid_area_ids_or_machine_ids_fails_validation(): void
    {
        $request = new PartNumberRequest();
        $data = [
            'pn_baan' => 'PN-TEST-VALID',
            'area_ids' => ['01JNONEXISTENTAREA000000000'],
            'machine_ids' => ['01JNONEXISTENTMACHINE0000000'],
        ];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('area_ids.0', $validator->errors()->toArray());
        $this->assertArrayHasKey('machine_ids.0', $validator->errors()->toArray());
    }
}

