<?php

namespace Tests\Unit;

use App\Support\Format;
use App\Support\NumberInput;
use App\Support\VerifyCode;
use PHPUnit\Framework\TestCase;

class NumbersTest extends TestCase
{
    public function test_typed_numbers_are_normalised(): void
    {
        $this->assertSame('1250000', NumberInput::normalize('1 250 000'));
        $this->assertSame('1250000.5', NumberInput::normalize("1\u{00A0}250\u{00A0}000,5"));
        $this->assertSame('1250000.50', NumberInput::normalize('1,250,000.50'));
        $this->assertNull(NumberInput::normalize('  '));
        $this->assertSame(12.5, NumberInput::float('12,5'));
    }

    public function test_numbers_and_phones_are_formatted(): void
    {
        $this->assertSame("1\u{00A0}250\u{00A0}000", Format::number(1250000));
        $this->assertSame("1\u{00A0}250,50", Format::number(1250.5, 2));
        $this->assertSame('+998 90 159-01-02', Format::phone('998901590102'));
        $this->assertSame('+998 90 159-01-02', Format::phone('90 159 01 02'));
        $this->assertSame('tel:+998901590102', Format::telLink('+998 90 159-01-02'));
    }

    public function test_verify_codes_are_normalised(): void
    {
        $this->assertSame('FV-7K3M-9Q2X', VerifyCode::normalize('fv 7k3m 9q2x'));
        $this->assertSame('FV-7K3M-9Q2X', VerifyCode::normalize('7K3M9Q2X'));
        $this->assertSame('', VerifyCode::normalize('FV-123'));
    }
}
