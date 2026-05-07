<?php

namespace Tests\Unit\Support;

use App\Support\SensitiveData;
use Tests\TestCase;

class SensitiveDataTest extends TestCase
{
    public function test_hash_returns_consistent_output_for_same_input(): void
    {
        $value = '1234567890123456';

        $hash1 = SensitiveData::hash($value);
        $hash2 = SensitiveData::hash($value);

        $this->assertSame($hash1, $hash2);
    }

    public function test_hash_returns_different_output_for_different_inputs(): void
    {
        $hash1 = SensitiveData::hash('1234567890123456');
        $hash2 = SensitiveData::hash('6543210987654321');

        $this->assertNotSame($hash1, $hash2);
    }

    public function test_hash_output_is_64_characters_hex_sha256(): void
    {
        $hash = SensitiveData::hash('1234567890123456');

        $this->assertNotNull($hash);
        $this->assertSame(64, strlen($hash));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hash);
    }

    public function test_hash_with_empty_string_returns_null(): void
    {
        $hash = SensitiveData::hash('');

        $this->assertNull($hash);
    }

    public function test_hash_with_null_returns_null(): void
    {
        $hash = SensitiveData::hash(null);

        $this->assertNull($hash);
    }

    public function test_hash_with_whitespace_only_string_returns_null(): void
    {
        $hash = SensitiveData::hash('   ');

        $this->assertNull($hash);
    }

    public function test_hash_trims_whitespace_before_hashing(): void
    {
        $hashWithSpaces = SensitiveData::hash('  1234567890  ');
        $hashWithout = SensitiveData::hash('1234567890');

        $this->assertSame($hashWithout, $hashWithSpaces);
    }

    public function test_hash_produces_valid_sha256_for_known_input(): void
    {
        $expected = hash('sha256', 'hello');
        $actual = SensitiveData::hash('hello');

        $this->assertSame($expected, $actual);
    }
}
