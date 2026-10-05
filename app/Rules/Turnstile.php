<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Checks a Cloudflare Turnstile token with Cloudflare's siteverify API. A token is single use, so the login page renders a fresh widget after every failed attempt.
 */
class Turnstile implements ValidationRule
{
    /**
     * Cloudflare's public test site key: the widget always passes. Used while TURNSTILE_ENABLED=false.
     */
    public const TEST_SITE_KEY = '1x00000000000000000000AA';

    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $passed = Http::asForm()->timeout(5)->post(self::VERIFY_URL, [
                'secret' => config('services.turnstile.secret_key'),
                'response' => $value,
                'remoteip' => request()->ip(),
            ])->json('success') === true;
        } catch (ConnectionException) {
            $passed = false;
        }

        if (! $passed) {
            $fail('Verifikasi keamanan gagal. Muat ulang halaman lalu coba lagi.');
        }
    }
}
