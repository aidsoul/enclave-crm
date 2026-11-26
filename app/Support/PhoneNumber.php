<?php

namespace App\Support;

class PhoneNumber
{
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $phone = trim($phone);
        if ($phone === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) < 5) {
            return null;
        }

        // Belarus: 11 digits starting with 80 (e.g. 80291234567 -> 375291234567), except 800 toll-free
        if (strlen($digits) === 11 && str_starts_with($digits, '80') && ! str_starts_with($digits, '800')) {
            $digits = '375'.substr($digits, 2);
        } elseif (strlen($digits) === 9 && in_array(substr($digits, 0, 2), ['25', '29', '33', '44', '17', '16', '15', '21', '22', '23'], true)) {
            // Belarus local 9 digits without country code (e.g. 291234567 -> 375291234567)
            $digits = '375'.$digits;
        } elseif (strlen($digits) === 11 && str_starts_with($digits, '8')) {
            // Russia & Kazakhstan: 11 digits starting with 8 -> convert to 7
            $digits = '7'.substr($digits, 1);
        } elseif (strlen($digits) === 10) {
            // Local 10 digits (e.g. 9991234567) -> assume +7
            $digits = '7'.$digits;
        }

        return $digits;
    }

    public static function withPlus(?string $phone): ?string
    {
        $normalized = self::normalize($phone);

        return $normalized !== null ? '+'.$normalized : null;
    }

    public static function format(?string $phone): ?string
    {
        $digits = self::normalize($phone);
        if (! $digits) {
            return null;
        }

        // Russia & Kazakhstan: 11 digits starting with 7
        if (strlen($digits) === 11 && str_starts_with($digits, '7')) {
            return sprintf(
                '+7 (%s) %s-%s-%s',
                substr($digits, 1, 3),
                substr($digits, 4, 3),
                substr($digits, 7, 2),
                substr($digits, 9, 2)
            );
        }

        // Belarus: 12 digits starting with 375
        if (strlen($digits) === 12 && str_starts_with($digits, '375')) {
            return sprintf(
                '+375 (%s) %s-%s-%s',
                substr($digits, 3, 2),
                substr($digits, 5, 3),
                substr($digits, 8, 2),
                substr($digits, 10, 2)
            );
        }

        // Ukraine: 12 digits starting with 380
        if (strlen($digits) === 12 && str_starts_with($digits, '380')) {
            return sprintf(
                '+380 (%s) %s-%s-%s',
                substr($digits, 3, 2),
                substr($digits, 5, 3),
                substr($digits, 8, 2),
                substr($digits, 10, 2)
            );
        }

        // Uzbekistan: 12 digits starting with 998
        if (strlen($digits) === 12 && str_starts_with($digits, '998')) {
            return sprintf(
                '+998 (%s) %s-%s-%s',
                substr($digits, 3, 2),
                substr($digits, 5, 3),
                substr($digits, 8, 2),
                substr($digits, 10, 2)
            );
        }

        // Georgia: 12 digits starting with 995
        if (strlen($digits) === 12 && str_starts_with($digits, '995')) {
            return sprintf(
                '+995 (%s) %s-%s-%s',
                substr($digits, 3, 3),
                substr($digits, 6, 2),
                substr($digits, 8, 2),
                substr($digits, 10, 2)
            );
        }

        // Armenia: 11 digits starting with 374
        if (strlen($digits) === 11 && str_starts_with($digits, '374')) {
            return sprintf(
                '+374 (%s) %s-%s-%s',
                substr($digits, 3, 2),
                substr($digits, 5, 2),
                substr($digits, 7, 2),
                substr($digits, 9, 2)
            );
        }

        // USA / Canada: 11 digits starting with 1
        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            return sprintf(
                '+1 (%s) %s-%s',
                substr($digits, 1, 3),
                substr($digits, 4, 3),
                substr($digits, 7, 4)
            );
        }

        // Any other international number
        return '+'.$digits;
    }

    public static function isValid(?string $phone): bool
    {
        return self::normalize($phone) !== null;
    }
}
