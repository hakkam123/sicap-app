<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\ConsumeApiRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ConsumeApiRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_consumes_payload_passes_validation(): void
    {
        $request = new ConsumeApiRequest();
        $data = [
            'consumes' => [
                [
                    'date' => '2026-03-14',
                    'part_number' => 'PN-SAMPLE-01',
                    'desc' => 'Sample description',
                    'qty' => 5,
                    'amount' => '150.000,00',
                ],
                [
                    'date' => '14-03-2026',
                    'part_number' => 'PN-SAMPLE-02',
                    'qty' => -2,
                    'amount' => '-50000',
                ]
            ],
        ];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertTrue($validator->passes());
    }

    public function test_missing_or_empty_consumes_payload_fails(): void
    {
        $request = new ConsumeApiRequest();

        $emptyData = ['consumes' => []];
        $validatorEmpty = Validator::make($emptyData, $request->rules(), $request->messages());
        $this->assertFalse($validatorEmpty->passes());
        $this->assertArrayHasKey('consumes', $validatorEmpty->errors()->toArray());

        $nullData = [];
        $validatorNull = Validator::make($nullData, $request->rules(), $request->messages());
        $this->assertFalse($validatorNull->passes());
        $this->assertArrayHasKey('consumes', $validatorNull->errors()->toArray());
    }

    public function test_exceeding_max_500_items_fails_validation(): void
    {
        $request = new ConsumeApiRequest();
        $items = [];
        for ($i = 0; $i < 501; $i++) {
            $items[] = [
                'date' => '2026-03-14',
                'part_number' => 'PN-' . $i,
                'qty' => 1,
                'amount' => 1000,
            ];
        }

        $validator = Validator::make(['consumes' => $items], $request->rules(), $request->messages());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('consumes', $validator->errors()->toArray());
        $this->assertEquals('Payload consumes maksimal berisi 500 rekaman data per request.', $validator->errors()->first('consumes'));
    }

    public function test_invalid_date_format_in_item_fails_validation(): void
    {
        $request = new ConsumeApiRequest();
        $data = [
            'consumes' => [
                [
                    'date' => 'invalid-date-string',
                    'part_number' => 'PN-SAMPLE-01',
                    'qty' => 1,
                    'amount' => 1000,
                ]
            ],
        ];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('consumes.0.date', $validator->errors()->toArray());
    }

    public function test_zero_quantity_or_invalid_amount_fails_validation(): void
    {
        $request = new ConsumeApiRequest();
        $data = [
            'consumes' => [
                [
                    'date' => '2026-03-14',
                    'part_number' => 'PN-SAMPLE-01',
                    'qty' => 0,
                    'amount' => 'invalid_amount_text',
                ]
            ],
        ];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('consumes.0.qty', $validator->errors()->toArray());
        $this->assertArrayHasKey('consumes.0.amount', $validator->errors()->toArray());
    }
}

