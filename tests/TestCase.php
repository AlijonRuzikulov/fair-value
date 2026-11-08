<?php

namespace Tests;

use App\Support\License;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** A signing key of the tests only (the real one is the seller's). */
    private static ?string $testKeys = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Every test runs with a valid licence unless it sets another one.
        $this->license(now()->addYear()->toDateString());
    }

    /**
     * Gives this copy a licence until the day, signed with the tests' key;
     * null leaves it without any key.
     */
    protected function license(?string $until, string $company = 'Sinov MChJ'): ?string
    {
        self::$testKeys ??= sodium_crypto_sign_keypair();
        config([
            'fairvalue.license.public_key' => base64_encode(sodium_crypto_sign_publickey(self::$testKeys)),
            'fairvalue.license.secret_key' => base64_encode(sodium_crypto_sign_secretkey(self::$testKeys)),
        ]);
        $key = $until ? License::issue($company, CarbonImmutable::parse($until)) : null;
        config(['fairvalue.license.secret_key' => null, 'fairvalue.license.key' => $key]);
        License::refresh();

        return $key;
    }

    /** Makes this copy the seller's own (it holds the secret half of the key). */
    protected function sellerCopy(): void
    {
        self::$testKeys ??= sodium_crypto_sign_keypair();
        config([
            'fairvalue.license.public_key' => base64_encode(sodium_crypto_sign_publickey(self::$testKeys)),
            'fairvalue.license.secret_key' => base64_encode(sodium_crypto_sign_secretkey(self::$testKeys)),
        ]);
        License::refresh();
    }
}
