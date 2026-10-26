<?php
declare(strict_types=1);

final class Validator
{
    public array $errors = [];
    public array $d = [];

    public function __construct(private array $in) {}

    public function ok(): bool { return !$this->errors; }
    public function fail(string $k, string $msg): void { $this->errors[$k] ??= $msg; }
    private function raw(string $k): string
    {
        $x = $this->in[$k] ?? '';
        return is_array($x) ? '' : trim((string)$x);
    }

    public function text(string $k, string $label, bool $req = false, int $max = 255, int $min = 0): ?string
    {
        $x = preg_replace('/\s+/u', ' ', $this->raw($k)) ?? '';
        return $this->finish($k, $label, $x, $req, $max, $min);
    }
    public function area(string $k, string $label, bool $req = false, int $max = 5000): ?string
    {
        return $this->finish($k, $label, str_replace("\r\n", "\n", $this->raw($k)), $req, $max, 0);
    }
    private function finish(string $k, string $label, string $x, bool $req, int $max, int $min): ?string
    {
        $this->d[$k] = null;
        if ($x === '') {
            $this->d[$k] = null;
            if ($req) $this->fail($k, "$label is required.");
            return null;
        }
        if (mb_strlen($x) > $max) $this->fail($k, "$label must be at most $max characters.");
        if ($min && mb_strlen($x) < $min) $this->fail($k, "$label must be at least $min characters.");
        return $this->d[$k] = $x;
    }
    public function email(string $k, string $label, bool $req = false): ?string
    {
        $x = $this->text($k, $label, $req, 150);
        if ($x !== null && !filter_var($x, FILTER_VALIDATE_EMAIL)) $this->fail($k, "$label is not a valid email address.");
        return $x === null ? null : mb_strtolower($x);
    }
    public function phone(string $k, string $label, bool $req = false): ?string
    {
        $x = $this->text($k, $label, $req, 30);
        if ($x !== null && !preg_match('/^\+?[0-9][0-9 ()\-]{6,24}$/', $x)) $this->fail($k, "$label is not a valid phone number.");
        return $x;
    }
    public function date(string $k, string $label, bool $req = false, ?string $notAfter = null, ?string $notBefore = null): ?string
    {
        $this->d[$k] = null;
        $x = $this->raw($k);
        if ($x === '') {
            $this->d[$k] = null;
            if ($req) $this->fail($k, "$label is required.");
            return null;
        }
        $dt = DateTime::createFromFormat('!Y-m-d', $x);
        if (!$dt || $dt->format('Y-m-d') !== $x || (int)$dt->format('Y') < 1900) {
            $this->fail($k, "$label is not a valid date.");
            return null;
        }
        if ($notAfter !== null && $x > $notAfter) $this->fail($k, "$label cannot be after " . fmt_date($notAfter) . '.');
        if ($notBefore !== null && $x < $notBefore) $this->fail($k, "$label cannot be before " . fmt_date($notBefore) . '.');
        return $this->d[$k] = $x;
    }
    public function enum(string $k, string $label, array $allowed, bool $req = false): ?string
    {
        $this->d[$k] = null;
        $x = $this->raw($k);
        if ($x === '') {
            $this->d[$k] = null;
            if ($req) $this->fail($k, "Please choose $label.");
            return null;
        }
        if (!in_array($x, array_map('strval', $allowed), true)) {
            $this->fail($k, "$label has an invalid value.");
            return null;
        }
        return $this->d[$k] = $x;
    }
    /** Money / decimal. Accepts "1,500,000.50". Returns a "0.00" string or null. */
    public function decimal(string $k, string $label, bool $req = false, float $min = 0.0, float $max = 9999999999999.0): ?string
    {
        $this->d[$k] = null;
        $x = str_replace([',', ' ', '₦'], '', $this->raw($k));
        if ($x === '') {
            $this->d[$k] = null;
            if ($req) $this->fail($k, "$label is required.");
            return null;
        }
        if (!preg_match('/^\d+(\.\d{1,2})?$/', $x)) {
            $this->fail($k, "$label must be a valid positive amount.");
            return null;
        }
        $f = (float)$x;
        if ($f < $min || $f > $max) {
            $this->fail($k, "$label is out of the allowed range.");
            return null;
        }
        return $this->d[$k] = number_format($f, 2, '.', '');
    }
    public function int(string $k, string $label, bool $req = false, int $min = 0, int $max = 255): ?int
    {
        $this->d[$k] = null;
        $x = $this->raw($k);
        if ($x === '') {
            $this->d[$k] = null;
            if ($req) $this->fail($k, "$label is required.");
            return null;
        }
        if (!preg_match('/^\d{1,9}$/', $x) || (int)$x < $min || (int)$x > $max) {
            $this->fail($k, "$label must be a whole number between $min and $max.");
            return null;
        }
        return $this->d[$k] = (int)$x;
    }
    public function bool(string $k): int { return $this->d[$k] = !empty($this->in[$k]) ? 1 : 0; }
}
