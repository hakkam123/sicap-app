<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\ConsumeRequest;
use App\Models\Area;
use App\Models\Machine;
use App\Models\PartNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ConsumeRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_consume_data_passes_validation(): void
    {
        $part = PartNumber::factory()->create();
        $area = Area::factory()->create();
        $machine = Machine::factory()->create(['area_id' => $area->id]);

        $request = new ConsumeRequest();
        $data = [
            'consumed_at' => '2026-03-14 09:30:00',
            'part_number_id' => $part->id,
            'quantity' => 5,
            'amount' => 75000.50,
            'area_id' => $area->id,
            'machine_id' => $machine->id,
        ];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertTrue($validator->passes());
    }

    public function test_missing_or_invalid_consumed_at_fails_validation(): void
    {
        $part = PartNumber::factory()->create();

        $request = new ConsumeRequest();
        $data = [
            'consumed_at' => 'not-a-valid-date',
            'part_number_id' => $part->id,
            'quantity' => 1,
            'amount' => 10000,
        ];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('consumed_at', $validator->errors()->toArray());
    }

    public function test_missing_or_invalid_part_number_id_fails_validation(): void
    {
        $request = new ConsumeRequest();
        $data = [
            'consumed_at' => '2026-03-14',
            'part_number_id' => '01JNONEXISTENTPART000000000',
            'quantity' => 1,
            'amount' => 10000,
        ];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('part_number_id', $validator->errors()->toArray());
        $this->assertEquals('Part Number tidak valid atau tidak ditemukan.', $validator->errors()->first('part_number_id'));
    }

    public function test_zero_quantity_or_non_integer_quantity_fails_validation(): void
    {
        $part = PartNumber::factory()->create();

        $request = new ConsumeRequest();
        $dataZero = [
            'consumed_at' => '2026-03-14',
            'part_number_id' => $part->id,
            'quantity' => 0,
            'amount' => 10000,
        ];

        $validatorZero = Validator::make($dataZero, $request->rules(), $request->messages());
        $this->assertFalse($validatorZero->passes());
        $this->assertArrayHasKey('quantity', $validatorZero->errors()->toArray());
        $this->assertEquals('Quantity pemakaian tidak boleh 0.', $validatorZero->errors()->first('quantity'));

        $dataFloat = [
            'consumed_at' => '2026-03-14',
            'part_number_id' => $part->id,
            'quantity' => 2.5,
            'amount' => 10000,
        ];

        $validatorFloat = Validator::make($dataFloat, $request->rules(), $request->messages());
        $this->assertFalse($validatorFloat->passes());
        $this->assertArrayHasKey('quantity', $validatorFloat->errors()->toArray());
    }

    public function test_zero_amount_or_non_numeric_amount_fails_validation(): void
    {
        $part = PartNumber::factory()->create();

        $request = new ConsumeRequest();
        $dataZero = [
            'consumed_at' => '2026-03-14',
            'part_number_id' => $part->id,
            'quantity' => 1,
            'amount' => 0,
        ];

        $validatorZero = Validator::make($dataZero, $request->rules(), $request->messages());
        $this->assertFalse($validatorZero->passes());
        $this->assertArrayHasKey('amount', $validatorZero->errors()->toArray());
        $this->assertEquals('Amount tidak boleh 0.', $validatorZero->errors()->first('amount'));
    }

    public function test_machine_not_in_chosen_area_fails_validation(): void
    {
        $part = PartNumber::factory()->create();
        $area1 = Area::factory()->create();
        $area2 = Area::factory()->create();
        $machineInArea2 = Machine::factory()->create(['area_id' => $area2->id]);

        $request = new ConsumeRequest();
        $request->merge(['area_id' => $area1->id]);

        $data = [
            'consumed_at' => '2026-03-14',
            'part_number_id' => $part->id,
            'quantity' => 1,
            'amount' => 50000,
            'area_id' => $area1->id,
            'machine_id' => $machineInArea2->id,
        ];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('machine_id', $validator->errors()->toArray());
    }
}

