<?php
declare(strict_types=1);

/** Business rules for house and land sales. Every figure is calculated on the server. */
final class Sale
{
    public const STATUS = [
        'negotiation'    => ['Negotiation', 'secondary'], 'reserved' => ['Reserved', 'warning'], 'deposit_paid' => ['Deposit paid', 'info'],
        'partially_paid' => ['Partially paid', 'primary'], 'fully_paid' => ['Fully paid', 'success'], 'completed' => ['Completed', 'dark'], 'cancelled' => ['Cancelled', 'danger'],
    ];
    public const OPEN = ['negotiation', 'reserved', 'deposit_paid', 'partially_paid', 'fully_paid'];

    public static function badge(string $s): string
    {
        $m = self::STATUS[$s] ?? [ucfirst($s), 'secondary'];
        return '<span class="badge badge-soft bg-' . $m[1] . '">' . e($m[0]) . '</span>';
    }

    public static function outstanding(array $s): string
    {
        return Money::fromKobo(max(0, Money::kobo($s['sale_price']) - Money::kobo($s['amount_paid'])));
    }

    /** Status the payments imply. Manual states (negotiation, reserved, completed, cancelled) are only left when money arrives. */
    public static function derive(array $s, int $paidKobo): string
    {
        if (in_array($s['status'], ['completed', 'cancelled'], true)) return $s['status'];
        $price = Money::kobo($s['sale_price']);
        $dep = Money::kobo($s['deposit']);
        if ($paidKobo >= $price) return 'fully_paid';
        if ($paidKobo > $dep) return 'partially_paid';
        if ($paidKobo > 0) return 'deposit_paid';
        return in_array($s['status'], ['deposit_paid', 'partially_paid', 'fully_paid'], true) ? 'reserved' : $s['status'];
    }

    /** Re-derive amount_paid and status after any payment change. Call inside the transaction that changed the payment. */
    public static function recalc(int $id): void
    {
        $s = db_row('SELECT * FROM sales WHERE id = ? FOR UPDATE', [$id]);
        if (!$s) return;
        $paid = Money::kobo(db_val("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE sale_id = ? AND status = 'valid'", [$id]));
        $new = self::derive($s, $paid);
        db_exec('UPDATE sales SET amount_paid = ?, status = ? WHERE id = ?', [Money::fromKobo($paid), $new, $id]);
        if ($new !== $s['status']) {
            Audit::log('status', 'sales', $id, "Sale {$s['ref']} status: {$s['status']} to $new (payments total " . money(Money::fromKobo($paid)) . ')');
            if (in_array($new, ['deposit_paid', 'partially_paid', 'fully_paid'], true)) self::setAsset($s, 'reserved');
        }
    }

    /** Move the property or plot being sold to a new status, keeping property status history. */
    public static function setAsset(array $s, string $to): void
    {
        if ($s['sale_type'] === 'property') {
            $p = db_row('SELECT id, status FROM properties WHERE id = ? FOR UPDATE', [(int)$s['property_id']]);
            if ($p && $p['status'] !== $to) Tenancy::setPropertyStatus((int)$p['id'], $p['status'], $to, 'Sale ' . $s['ref'] . ': ' . $to);
        } else {
            db_exec('UPDATE land_plots SET status = ? WHERE id = ?', [$to, (int)$s['plot_id']]);
        }
    }

    /** An open sale (not cancelled or completed) already holding this asset, other than $exceptId. */
    public static function openFor(string $type, int $assetId, int $exceptId = 0): ?array
    {
        $col = $type === 'property' ? 'property_id' : 'plot_id';
        return db_row("SELECT id, ref, status FROM sales WHERE $col = ? AND status NOT IN ('cancelled','completed') AND id <> ? LIMIT 1", [$assetId, $exceptId]);
    }

    /** Agreed instalments, with payments applied oldest first. */
    public static function schedule(array $s, ?string $today = null): array
    {
        $today ??= date('Y-m-d');
        $pool = Money::kobo($s['amount_paid']);
        $rows = []; $overdue = 0; $next = null; $nextAmt = 0;
        foreach (db_all('SELECT * FROM sale_installments WHERE sale_id = ? ORDER BY due_date, id', [(int)$s['id']]) as $i) {
            $amt = Money::kobo($i['amount']);
            $cov = min($pool, $amt); $pool -= $cov; $rem = $amt - $cov;
            $st = $rem === 0 ? 'paid' : ($i['due_date'] < $today ? 'overdue' : ($cov > 0 ? 'partial' : 'upcoming'));
            if ($rem > 0 && $i['due_date'] < $today) $overdue += $rem;
            if ($rem > 0 && $next === null) { $next = $i['due_date']; $nextAmt = $rem; }
            $rows[] = ['id' => $i['id'], 'due' => $i['due_date'], 'label' => $i['label'], 'amount' => $i['amount'], 'paid' => Money::fromKobo($cov), 'remaining' => Money::fromKobo($rem), 'status' => $st];
        }
        return ['rows' => $rows, 'overdue' => Money::fromKobo($overdue), 'next_due' => $next, 'next_amount' => Money::fromKobo($nextAmt)];
    }

    /** Open sales that have an instalment past its due date and not fully covered. */
    public static function overdueList(): array
    {
        static $c = null;
        if ($c !== null) return $c;
        $out = [];
        $rows = db_all("SELECT s.*, bc.full_name buyer_name, COALESCE(pr.title, CONCAT('Plot ', lp.plot_number)) asset_title FROM sales s
            JOIN customers bc ON bc.id = s.buyer_id LEFT JOIN properties pr ON pr.id = s.property_id LEFT JOIN land_plots lp ON lp.id = s.plot_id
            WHERE s.status IN ('reserved','deposit_paid','partially_paid') AND EXISTS (SELECT 1 FROM sale_installments i WHERE i.sale_id = s.id AND i.due_date < CURDATE()) LIMIT 300");
        foreach ($rows as $r) {
            $sc = self::schedule($r);
            if (Money::kobo($sc['overdue']) > 0) $out[] = $r + ['overdue' => $sc['overdue']];
        }
        return $c = $out;
    }

    public static function assetLabel(array $s): string
    {
        return $s['sale_type'] === 'property' ? (string)($s['property_title'] ?? 'Property') : (string)($s['plot_label'] ?? 'Plot');
    }

    public static function plotLabel(array $p): string
    {
        $bits = [];
        if (!empty($p['block_number'])) $bits[] = 'Block ' . $p['block_number'];
        $bits[] = 'Plot ' . $p['plot_number'];
        $l = implode(', ', $bits);
        return !empty($p['estate_name']) ? $l . ' · ' . $p['estate_name'] : $l . (!empty($p['area']) ? ' · ' . $p['area'] : '');
    }
}
