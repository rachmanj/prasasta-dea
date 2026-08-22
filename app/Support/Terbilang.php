<?php

namespace App\Support;

class Terbilang
{
    private const UNITS = [
        '', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan',
        'Sepuluh', 'Sebelas', 'Dua Belas', 'Tiga Belas', 'Empat Belas', 'Lima Belas',
        'Enam Belas', 'Tujuh Belas', 'Delapan Belas', 'Sembilan Belas',
    ];

    public static function rupiah(float|int $amount): string
    {
        $amount = (int) round(abs($amount));

        if ($amount === 0) {
            return 'Nol Rupiah';
        }

        return trim(self::convert($amount)).' Rupiah';
    }

    private static function convert(int $n): string
    {
        if ($n < 20) {
            return self::UNITS[$n];
        }

        if ($n < 100) {
            $tens = (int) floor($n / 10);
            $rest = $n % 10;

            return self::tens($tens, $rest);
        }

        if ($n < 200) {
            $rest = $n - 100;

            return 'Seratus'.($rest > 0 ? ' '.self::convert($rest) : '');
        }

        if ($n < 1000) {
            $hundreds = (int) floor($n / 100);
            $rest = $n % 100;

            return self::UNITS[$hundreds].' Ratus'.($rest > 0 ? ' '.self::convert($rest) : '');
        }

        if ($n < 2000) {
            $rest = $n - 1000;

            return 'Seribu'.($rest > 0 ? ' '.self::convert($rest) : '');
        }

        if ($n < 1_000_000) {
            $thousands = (int) floor($n / 1000);
            $rest = $n % 1000;

            return self::convert($thousands).' Ribu'.($rest > 0 ? ' '.self::convert($rest) : '');
        }

        if ($n < 1_000_000_000) {
            $millions = (int) floor($n / 1_000_000);
            $rest = $n % 1_000_000;

            return self::convert($millions).' Juta'.($rest > 0 ? ' '.self::convert($rest) : '');
        }

        $billions = (int) floor($n / 1_000_000_000);
        $rest = $n % 1_000_000_000;

        return self::convert($billions).' Miliar'.($rest > 0 ? ' '.self::convert($rest) : '');
    }

    private static function tens(int $tens, int $rest): string
    {
        if ($tens === 1) {
            return self::UNITS[10 + $rest];
        }

        $word = self::UNITS[$tens].' Puluh';

        if ($rest > 0) {
            $word .= ' '.self::UNITS[$rest];
        }

        return $word;
    }
}
