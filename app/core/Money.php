<?php
declare(strict_types=1);

final class Money
{
    /** "1200000.50" -> integer kobo, avoiding float drift. */
    public static function kobo(string|float|int|null $amount): int
    {
        return (int)round(((float)$amount) * 100);
    }
    public static function fromKobo(int $k): string
    {
        return number_format($k / 100, 2, '.', '');
    }

    private const ONES = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen',
        'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    private const TENS = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    private static function below1000(int $n): string
    {
        $out = [];
        if ($n >= 100) {
            $out[] = self::ONES[intdiv($n, 100)] . ' Hundred';
            $n %= 100;
            if ($n) $out[] = 'and';
        }
        if ($n >= 20) {
            $out[] = self::TENS[intdiv($n, 10)] . ($n % 10 ? '-' . self::ONES[$n % 10] : '');
        } elseif ($n > 0) {
            $out[] = self::ONES[$n];
        }
        return implode(' ', $out);
    }

    public static function numberWords(int $n): string
    {
        if ($n === 0) return 'Zero';
        $scales = [1_000_000_000_000 => 'Trillion', 1_000_000_000 => 'Billion', 1_000_000 => 'Million', 1_000 => 'Thousand'];
        $parts = [];
        foreach ($scales as $v => $name) {
            if ($n >= $v) {
                $parts[] = self::below1000(intdiv($n, $v)) . ' ' . $name;
                $n %= $v;
            }
        }
        if ($n > 0) {
            $tail = self::below1000($n);
            $parts[] = ($parts && $n < 100 ? 'and ' : '') . $tail;
        }
        return implode(' ', $parts);
    }

    /** 1200000.50 -> "One Million Two Hundred Thousand Naira, Fifty Kobo Only" */
    public static function words(string|float|int $amount): string
    {
        $k = self::kobo($amount);
        $naira = intdiv($k, 100);
        $kobo = $k % 100;
        $s = self::numberWords($naira) . ' Naira';
        if ($kobo) $s .= ', ' . self::numberWords($kobo) . ' Kobo';
        return $s . ' Only';
    }
}
