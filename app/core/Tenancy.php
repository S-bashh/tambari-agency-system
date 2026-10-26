<?php
declare(strict_types=1);

/** Business rules for tenancies. The server is the single source of truth for every figure. */
final class Tenancy
{
    public const PERIOD_MONTHS = ['monthly' => 1, 'quarterly' => 3, 'biannual' => 6, 'annual' => 12];
    public const FREQ_LABEL = ['monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'biannual' => 'Every 6 months', 'annual' => 'Yearly'];
    public const FREQ_TEXT = ['monthly' => 'per month', 'quarterly' => 'per quarter', 'biannual' => 'every six months', 'annual' => 'per annum'];

    public static function addMonths(string $date, int $months): string
    {
        $d = new DateTimeImmutable($date);
        $y = (int)$d->format('Y');
        $m = (int)$d->format('n') - 1 + $months;
        $y += intdiv($m, 12);
        $m = $m % 12 + 1;
        $day = min((int)$d->format('j'), (int)(new DateTimeImmutable(sprintf('%04d-%02d-01', $y, $m)))->format('t'));
        return sprintf('%04d-%02d-%02d', $y, $m, $day);
    }
    /** Last day of the term: start + duration - 1 day. */
    public static function endDate(string $start, int $months): string
    {
        return (new DateTimeImmutable(self::addMonths($start, $months)))->modify('-1 day')->format('Y-m-d');
    }
    public static function periods(int $durationMonths, string $freq): int
    {
        $p = self::PERIOD_MONTHS[$freq] ?? 12;
        return ($durationMonths % $p === 0) ? intdiv($durationMonths, $p) : 0;
    }
    /** @return array{periods:int,rent_total:string,total_payable:string} all server-side, kobo-exact */
    public static function totals(array $d): array
    {
        $periods = self::periods((int)$d['duration_months'], (string)$d['rent_frequency']);
        $rent = Money::kobo($d['rent_amount']) * $periods;
        $fees = 0;
        foreach (['security_deposit', 'agency_fee', 'legal_fee', 'service_charge', 'other_fees'] as $f) $fees += Money::kobo($d[$f] ?? 0);
        return ['periods' => $periods, 'rent_total' => Money::fromKobo($rent), 'total_payable' => Money::fromKobo($rent + $fees)];
    }

    /**
     * Instalment schedule. Fees and the first rent period fall due on the start date; each later rent period
     * falls due at its own date. Payments are applied oldest-first.
     * @return array{rows:array,next_due:?string,next_amount:string,overdue:string,overdue_since:?string,outstanding:string}
     */
    public static function schedule(array $t, ?string $today = null): array
    {
        $today ??= date('Y-m-d');
        $periods = max(1, self::periods((int)$t['duration_months'], (string)$t['rent_frequency']));
        $step = self::PERIOD_MONTHS[$t['rent_frequency']];
        $rent = Money::kobo($t['rent_amount']);
        $fees = 0;
        foreach (['security_deposit', 'agency_fee', 'legal_fee', 'service_charge', 'other_fees'] as $f) $fees += Money::kobo($t[$f] ?? 0);
        $pool = Money::kobo($t['amount_paid']);
        $rows = []; $overdue = 0; $overdueSince = null; $next = null; $nextAmt = 0;
        for ($i = 0; $i < $periods; $i++) {
            $due = self::addMonths($t['start_date'], $i * $step);
            $amt = $rent + ($i === 0 ? $fees : 0);
            $cov = min($pool, $amt);
            $pool -= $cov;
            $rem = $amt - $cov;
            $status = $rem === 0 ? 'paid' : ($due < $today ? 'overdue' : ($cov > 0 ? 'partial' : 'upcoming'));
            if ($rem > 0 && $due < $today) {
                $overdue += $rem;
                $overdueSince ??= $due;
            }
            if ($rem > 0 && $next === null) { $next = $due; $nextAmt = $rem; }
            $rows[] = ['due' => $due, 'amount' => Money::fromKobo($amt), 'paid' => Money::fromKobo($cov), 'remaining' => Money::fromKobo($rem), 'status' => $status,
                       'label' => $i === 0 ? ($fees ? 'Rent + fees' : 'Rent') : 'Rent'];
        }
        return ['rows' => $rows, 'next_due' => $next, 'next_amount' => Money::fromKobo($nextAmt), 'overdue' => Money::fromKobo($overdue), 'overdue_since' => $overdueSince,
                'outstanding' => Money::fromKobo(max(0, Money::kobo($t['total_payable']) - Money::kobo($t['amount_paid'])))];
    }

    public static function outstanding(array $t): string
    {
        return Money::fromKobo(max(0, Money::kobo($t['total_payable']) - Money::kobo($t['amount_paid'])));
    }
    public static function daysRemaining(array $t, ?string $today = null): int
    {
        return (int)((new DateTimeImmutable($today ?? date('Y-m-d')))->diff(new DateTimeImmutable($t['end_date']))->format('%r%a'));
    }
    /** draft | active | expiring | expired | terminated | renewed  (expiry is derived from dates, no scheduled job needed) */
    public static function effective(array $t, ?string $today = null): string
    {
        if ($t['status'] !== 'active') return $t['status'];
        $days = self::daysRemaining($t, $today);
        if ($days < 0) return 'expired';
        return $days <= (int)setting('expiry_alert_days', '90') ? 'expiring' : 'active';
    }
    public static function effectiveSql(string $a = 't'): string
    {
        $d = max(1, (int)setting('expiry_alert_days', '90'));
        return "CASE WHEN $a.status = 'active' AND $a.end_date < CURDATE() THEN 'expired'
                     WHEN $a.status = 'active' AND DATEDIFF($a.end_date, CURDATE()) <= $d THEN 'expiring'
                     ELSE $a.status END";
    }
    public static function badge(string $eff): string
    {
        $m = ['draft' => ['Draft', 'secondary'], 'active' => ['Active', 'success'], 'expiring' => ['Expiring', 'warning'], 'expired' => ['Expired', 'danger'],
              'terminated' => ['Terminated', 'dark'], 'renewed' => ['Renewed', 'info']][$eff] ?? [ucfirst($eff), 'secondary'];
        return '<span class="badge badge-soft bg-' . $m[1] . '">' . e($m[0]) . '</span>';
    }
    public static function statuses(): array
    {
        return ['draft' => 'Draft', 'active' => 'Active', 'expiring' => 'Expiring', 'expired' => 'Expired', 'terminated' => 'Terminated', 'renewed' => 'Renewed'];
    }
    /** Re-derive amount_paid from valid payments. Call inside the same transaction that changed payments. */
    public static function recalc(int $id): void
    {
        db_exec("UPDATE tenancies SET amount_paid = (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE tenancy_id = ? AND status = 'valid') WHERE id = ?", [$id, $id]);
    }

    /** Keeps a property's Available/Reserved status in step with whether a draft tenancy is pending. */
    public static function syncReserved(int $propertyId): void
    {
        $p = db_row('SELECT id, status FROM properties WHERE id = ? FOR UPDATE', [$propertyId]);
        if (!$p || !in_array($p['status'], ['available', 'reserved'], true)) return;
        $hasDraft = (int)db_val("SELECT COUNT(*) FROM tenancies WHERE property_id = ? AND status = 'draft' AND deleted_at IS NULL", [$propertyId]) > 0
            || (int)db_val("SELECT COUNT(*) FROM sales WHERE property_id = ? AND status IN ('reserved','deposit_paid','partially_paid','fully_paid')", [$propertyId]) > 0;
        $want = $hasDraft ? 'reserved' : 'available';
        if ($want !== $p['status']) self::setPropertyStatus($propertyId, $p['status'], $want, $hasDraft ? 'Tenancy drafted' : 'Draft tenancy removed');
    }
    public static function setPropertyStatus(int $propertyId, ?string $from, string $to, string $note): void
    {
        db_exec('UPDATE properties SET status = ? WHERE id = ?', [$to, $propertyId]);
        db_exec('INSERT INTO property_status_log (property_id, from_status, to_status, note, changed_by) VALUES (?,?,?,?,?)', [$propertyId, $from, $to, $note, Auth::id()]);
    }

    /** Active tenancies with unpaid instalments past their due date. Computed from the schedule, no background job needed. */
    public static function overdueList(int $limit = 500): array
    {
        static $cache = null;
        if ($cache !== null) return $cache;
        $rows = db_all("SELECT t.*, tc.full_name tenant_name, p.ref property_ref, p.title property_title
            FROM tenancies t JOIN customers tc ON tc.id = t.tenant_id JOIN properties p ON p.id = t.property_id
            WHERE t.status = 'active' AND t.deleted_at IS NULL AND t.amount_paid < t.total_payable AND t.start_date < CURDATE() ORDER BY t.start_date LIMIT " . (int)$limit);
        $out = [];
        foreach ($rows as $r) {
            $s = self::schedule($r);
            if (Money::kobo($s['overdue']) > 0) $out[] = $r + ['overdue' => $s['overdue'], 'overdue_since' => $s['overdue_since']];
        }
        usort($out, fn($a, $b) => strcmp((string)$a['overdue_since'], (string)$b['overdue_since']));
        return $cache = $out;
    }
}
