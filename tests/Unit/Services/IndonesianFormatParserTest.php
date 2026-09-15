<?php

namespace Tests\Unit\Services;

use App\Support\IndonesianFormatParser;
use Carbon\Carbon;
use Tests\TestCase;

class IndonesianFormatParserTest extends TestCase
{
    public function test_parse_date_with_various_formats(): void
    {
        // 1. Indonesian month names
        $date1 = IndonesianFormatParser::parseDate('6 juli 2026');
        $this->assertNotNull($date1);
        $this->assertEquals('2026-07-06', $date1->format('Y-m-d'));

        $date2 = IndonesianFormatParser::parseDate('15-Agu-2026');
        $this->assertNotNull($date2);
        $this->assertEquals('2026-08-15', $date2->format('Y-m-d'));

        // 2. Slash format (DD/MM/YYYY)
        $date3 = IndonesianFormatParser::parseDate('25/12/2026');
        $this->assertNotNull($date3);
        $this->assertEquals('2026-12-25', $date3->format('Y-m-d'));

        // 3. ISO format (YYYY-MM-DD)
        $date4 = IndonesianFormatParser::parseDate('2026-05-20');
        $this->assertNotNull($date4);
        $this->assertEquals('2026-05-20', $date4->format('Y-m-d'));

        // 4. DateTime object
        $carbon = Carbon::create(2026, 11, 10);
        $date5 = IndonesianFormatParser::parseDate($carbon);
        $this->assertNotNull($date5);
        $this->assertEquals('2026-11-10', $date5->format('Y-m-d'));
    }

    public function test_parse_date_returns_null_on_invalid_or_empty_values(): void
    {
        $this->assertNull(IndonesianFormatParser::parseDate(null));
        $this->assertNull(IndonesianFormatParser::parseDate(''));
        $this->assertNull(IndonesianFormatParser::parseDate('   '));
        $this->assertNull(IndonesianFormatParser::parseDate('random-gibberish-string'));
    }

    public function test_parse_amount_with_various_indonesian_and_standard_formats(): void
    {
        // 1. Positive Indonesian thousands dot
        $this->assertSame(1386000.0, IndonesianFormatParser::parseAmount('1.386.000'));

        // 2. Positive Indonesian thousands dot and comma decimal
        $this->assertSame(1386000.5, IndonesianFormatParser::parseAmount('1.386.000,50'));

        // 3. Negative with hyphen and spaces
        $this->assertSame(-1386000.0, IndonesianFormatParser::parseAmount('-1.386.000'));
        $this->assertSame(-1386000.0, IndonesianFormatParser::parseAmount('- 1.386.000'));

        // 4. Negative with parentheses format (Accounting)
        $this->assertSame(-50000.0, IndonesianFormatParser::parseAmount('(50.000)'));

        // 5. Raw numeric inputs (int & float)
        $this->assertSame(25000.0, IndonesianFormatParser::parseAmount(25000));
        $this->assertSame(1234.56, IndonesianFormatParser::parseAmount(1234.56));
    }

    public function test_parse_amount_returns_null_on_empty_or_invalid_strings(): void
    {
        $this->assertNull(IndonesianFormatParser::parseAmount(null));
        $this->assertNull(IndonesianFormatParser::parseAmount(''));
        $this->assertNull(IndonesianFormatParser::parseAmount('   '));
        $this->assertNull(IndonesianFormatParser::parseAmount('abc-def'));
    }

    public function test_parse_quantity_with_positive_negative_and_edge_values(): void
    {
        $this->assertSame(10, IndonesianFormatParser::parseQty('10'));
        $this->assertSame(-5, IndonesianFormatParser::parseQty('-5'));
        $this->assertSame(-8, IndonesianFormatParser::parseQty('- 8'));
        $this->assertSame(-12, IndonesianFormatParser::parseQty('(12)'));
        $this->assertSame(100, IndonesianFormatParser::parseQty(100));
        $this->assertSame(0, IndonesianFormatParser::parseQty(''));
        $this->assertSame(0, IndonesianFormatParser::parseQty(null));
    }
}

