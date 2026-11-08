<?php

namespace App\Support;

use App\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Throwable;

/**
 * The licence of this copy of the system: whose it is and the day it ends.
 * The key is signed by the seller (Ed25519) and checked against the public
 * key in config/fairvalue.php, so a company cannot write a longer date into
 * it. The seller's own copy (with LICENSE_SECRET_KEY, the other half of that
 * key) is always licensed and issues the keys.
 *
 * After the end the system opens for viewing only (see EnsureLicenseAllowsChanges).
 */
final class License
{
    /** Days before the end when the warning appears. */
    public const WARN_DAYS = 30;

    private const PREFIX = 'FV1';

    /**
     * Keys once had a plan; every key now opens everything. New keys still
     * carry the full one, so that copies not yet updated read them as such.
     */
    private const FULL_PLAN = 'premium';

    private function __construct(
        public readonly string $state,
        public readonly ?string $company = null,
        public readonly ?CarbonImmutable $until = null,
    ) {}

    /** The licence of this copy (read once per request). */
    public static function current(): self
    {
        return app()->bound(self::class) ? app(self::class) : tap(self::resolve(), fn ($license) => app()->instance(self::class, $license));
    }

    /** Forgets the licence read in this request (after a new key is saved); it is read again when asked for. */
    public static function refresh(): void
    {
        app()->forgetInstance(self::class);
    }

    private static function resolve(): self
    {
        if (self::isSeller()) {
            return new self('seller', (string) config('app.name'));
        }

        try {
            $saved = Setting::get('license_key');
        } catch (Throwable) {
            $saved = null; // before the database is set up
        }
        $key = trim((string) ($saved ?: config('fairvalue.license.key')));
        if ($key === '') {
            return new self('missing');
        }

        $payload = self::verify($key);
        if ($payload === null) {
            return new self('invalid');
        }

        $until = CarbonImmutable::parse($payload['u'], config('app.timezone'))->endOfDay();

        return new self($until->isPast() ? 'expired' : 'active', $payload['c'], $until);
    }

    /** Whether this is the seller's copy: its secret key is the other half of the public key. */
    public static function isSeller(): bool
    {
        $secret = base64_decode((string) config('fairvalue.license.secret_key'), true);
        if ($secret === false || strlen($secret) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            return false;
        }

        return hash_equals(self::publicKey() ?? '', sodium_crypto_sign_publickey_from_secretkey($secret));
    }

    /**
     * A key for a company, signed with the seller's secret key.
     */
    public static function issue(string $company, CarbonImmutable $until): string
    {
        $secret = base64_decode((string) config('fairvalue.license.secret_key'), true);
        if (! self::isSeller() || $secret === false) {
            throw new \RuntimeException('Only the seller\'s copy issues licences.');
        }
        $payload = self::encode(json_encode([
            'c' => Str::limit(trim($company), 150, ''),
            'p' => self::FULL_PLAN,
            'u' => $until->toDateString(),
            'i' => now()->toDateString(),
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return self::PREFIX.'.'.$payload.'.'.self::encode(sodium_crypto_sign_detached(self::PREFIX.'.'.$payload, $secret));
    }

    /**
     * The contents of a key when its signature is right.
     *
     * @return array{c: string, u: string, p?: string, i?: string}|null
     */
    public static function verify(string $key): ?array
    {
        $parts = explode('.', trim($key));
        $public = self::publicKey();
        if (count($parts) !== 3 || $parts[0] !== self::PREFIX || $public === null) {
            return null;
        }
        try {
            $signature = self::decode($parts[2]);
            if (strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES || ! sodium_crypto_sign_verify_detached($signature, $parts[0].'.'.$parts[1], $public)) {
                return null;
            }
            $payload = json_decode(self::decode($parts[1]), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        $valid = is_array($payload)
            && is_string($payload['c'] ?? null)
            && is_string($payload['u'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $payload['u']);

        return $valid ? $payload : null;
    }

    private static function publicKey(): ?string
    {
        $key = base64_decode((string) config('fairvalue.license.public_key'), true);

        return $key !== false && strlen($key) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES ? $key : null;
    }

    private static function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private static function decode(string $text): string
    {
        $bytes = base64_decode(strtr($text, '-_', '+/'), true);
        if ($bytes === false) {
            throw new \InvalidArgumentException('Not base64.');
        }

        return $bytes;
    }

    /** New objects can be added and changed (the licence is valid and not over). */
    public function allowsChanges(): bool
    {
        return in_array($this->state, ['seller', 'active'], true);
    }

    public function daysLeft(): ?int
    {
        return $this->until ? (int) max(0, now()->startOfDay()->diffInDays($this->until->startOfDay(), false)) : null;
    }

    /** The end is near: the warning is shown. */
    public function endsSoon(): bool
    {
        return $this->state === 'active' && $this->daysLeft() !== null && $this->daysLeft() <= self::WARN_DAYS;
    }
}
