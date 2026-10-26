<?php
declare(strict_types=1);

/** Builds the receipt document. The same markup feeds the on-screen/print view and the PDF. */
final class Receipt
{
    public static function load(int $receiptId): ?array
    {
        return db_row("SELECT r.id receipt_id, r.receipt_no, r.issued_at, r.print_count, p.id payment_id, p.txn_id, p.payment_type, p.amount, p.payment_date, p.method, p.reference, p.purpose,
                p.received_by, p.notes, p.status, p.void_reason, p.voided_at, p.tenancy_id,
                c.full_name customer_name, c.phone customer_phone, pr.title property_title, pr.ref property_ref, pr.address property_address, pr.area property_area,
                t.ref tenancy_ref, t.total_payable, t.amount_paid, p.sale_id, sl.ref sale_ref, sl.sale_price, sl.amount_paid sale_paid,
                lp.plot_number, lp.block_number, lp.area plot_area, lp.ref plot_ref, le.name estate_name, vu.full_name voided_by_name
            FROM receipts r JOIN payments p ON p.id = r.payment_id JOIN customers c ON c.id = p.customer_id
            LEFT JOIN properties pr ON pr.id = p.property_id LEFT JOIN tenancies t ON t.id = p.tenancy_id LEFT JOIN sales sl ON sl.id = p.sale_id
            LEFT JOIN land_plots lp ON lp.id = sl.plot_id LEFT JOIN land_estates le ON le.id = lp.estate_id LEFT JOIN users vu ON vu.id = p.voided_by WHERE r.id = ?", [$receiptId]);
    }

    public static function css(bool $pdf): string
    {
        return '@page{size:A5 landscape;margin:8mm}
        body{font-family:"DejaVu Sans",Arial,sans-serif;font-size:9.5pt;color:#111;margin:0;' . ($pdf ? '' : 'background:#fff;padding:14px') . '}
        .rc{border:2px solid #1f5fbf;border-radius:10px;padding:10px 14px;' . ($pdf ? '' : 'max-width:820px;margin:0 auto;') . '}
        table{border-collapse:collapse;width:100%}td{vertical-align:top}
        .head td{padding:0 0 6px}.co{font-size:16pt;font-weight:bold;color:#12263f;line-height:1.1}.sub{font-size:7.5pt;color:#444}
        .no{text-align:right;font-size:9pt}.no .n{font-size:12pt;font-weight:bold;color:#c0245a}
        .title{background:#c0245a;color:#fff;text-align:center;font-weight:bold;letter-spacing:.5px;padding:4px 0;margin:4px 0 8px;font-size:10pt}
        .body td{padding:4px 0;border-bottom:1px solid #1f5fbf;font-size:9.5pt}.lab{width:23%;font-style:italic;color:#12263f;white-space:nowrap}
        .box{border:2px solid #1f5fbf;padding:7px 10px;font-size:14pt;font-weight:bold;color:#12263f;text-align:center}
        .foot td{padding-top:12px}.sign{border-top:1px solid #111;text-align:center;font-size:8pt;padding-top:3px}
        .note{font-size:7.2pt;color:#1f5fbf;text-align:center;margin-top:8px}
        .void{border:2px solid #b00020;color:#b00020;text-align:center;font-weight:bold;padding:4px;margin-bottom:6px;font-size:9pt}
        .meta{font-size:7.5pt;color:#555;margin-top:4px}';
    }

    public static function html(array $r, bool $pdf): string
    {
        $logo = $pdf ? Pdf::logoDataUri() : logo_url();
        $amt = Money::kobo($r['amount']);
        $fig = '&#8358; ' . number_format(intdiv($amt, 100)) . ' : ' . str_pad((string)($amt % 100), 2, '0', STR_PAD_LEFT) . ' K';
        $methods = PaymentsController::methods();
        $types = PaymentsController::types();
        $prop = $r['property_title'] ? $r['property_title'] . ' (' . $r['property_ref'] . ')' . ($r['property_area'] ? ', ' . $r['property_area'] : '') : '';
        if (!$prop && $r['plot_number']) $prop = Sale::plotLabel($r + ['area' => $r['plot_area']]) . ' (' . $r['plot_ref'] . ')';
        $bal = '';
        if ($r['tenancy_id'] && $r['status'] === 'valid') {
            $bal = '<tr><td class="lab">Tenancy balance:</td><td>' . e($r['tenancy_ref']) . ' &middot; paid ' . e(money($r['amount_paid'])) . ' of ' . e(money($r['total_payable'])) . ' &middot; outstanding ' . e(money(Tenancy::outstanding($r))) . '</td></tr>';
        }
        if ($r['sale_id'] && $r['status'] === 'valid') {
            $bal = '<tr><td class="lab">Sale balance:</td><td>' . e($r['sale_ref']) . ' &middot; paid ' . e(money($r['sale_paid'])) . ' of ' . e(money($r['sale_price'])) . ' &middot; outstanding ' . e(money(Money::fromKobo(max(0, Money::kobo($r['sale_price']) - Money::kobo($r['sale_paid']))))) . '</td></tr>';
        }
        $h = '<div class="rc">';
        if ($r['status'] === 'void') {
            $h .= '<div class="void">VOID &mdash; this receipt was cancelled on ' . e(fmt_date($r['voided_at'])) . ($r['voided_by_name'] ? ' by ' . e($r['voided_by_name']) : '') . '. Reason: ' . e($r['void_reason']) . '</div>';
        }
        $h .= '<table class="head"><tr>' . ($logo ? '<td style="width:62px"><img src="' . e($logo) . '" style="height:52px"></td>' : '')
            . '<td><div class="co">' . e(setting('company_name')) . '</div><div class="sub">' . e(setting('company_tagline')) . '<br>' . e(setting('company_address'))
            . '<br>Tel: ' . e(setting('company_phone')) . (setting('company_reg') ? ' &nbsp;|&nbsp; ' . e(setting('company_reg')) : '') . '</div></td>'
            . '<td class="no" style="width:30%">Receipt No.<br><span class="n">' . e($r['receipt_no']) . '</span><br>Date: <strong>' . e(fmt_date($r['payment_date'])) . '</strong></td></tr></table>';
        $h .= '<div class="title">' . e(setting('receipt_title', 'PAYMENT RECEIPT')) . '</div>';
        $h .= '<table class="body">'
            . '<tr><td class="lab">Received from:</td><td><strong>' . e($r['customer_name']) . '</strong></td></tr>'
            . '<tr><td class="lab">The sum of:</td><td>' . e(Money::words($r['amount'])) . '</td></tr>'
            . '<tr><td class="lab">Being payment for:</td><td>' . e($r['purpose'] ?: ($types[$r['payment_type']] ?? $r['payment_type'])) . '</td></tr>'
            . ($prop ? '<tr><td class="lab">Property:</td><td>' . e($prop) . '</td></tr>' : '')
            . '<tr><td class="lab">Payment method:</td><td>' . e($methods[$r['method']] ?? $r['method']) . ($r['reference'] ? ' &middot; Ref: ' . e($r['reference']) : '') . '</td></tr>'
            . $bal . ($r['notes'] ? '<tr><td class="lab">Notes:</td><td>' . e($r['notes']) . '</td></tr>' : '')
            . '</table>';
        $h .= '<table class="foot"><tr><td style="width:34%"><div class="box">' . $fig . '</div></td><td style="width:6%"></td>'
            . '<td style="width:28%"><div style="height:26px"></div><div class="sign">Payer\'s Signature</div></td><td style="width:4%"></td>'
            . '<td style="width:28%"><div style="height:26px;font-size:8pt;text-align:center">' . e($r['received_by']) . '</div><div class="sign">For: ' . e(setting('company_name')) . '</div></td></tr></table>';
        if (setting('receipt_footer') !== '') $h .= '<div class="note">' . e(setting('receipt_footer')) . '</div>';
        $h .= '<div class="meta">Txn ' . e($r['txn_id']) . ' &middot; issued ' . e(fmt_datetime($r['issued_at'])) . '</div></div>';
        return $h;
    }

    public static function page(array $r, bool $pdf, bool $autoPrint = false): string
    {
        $js = $autoPrint ? '<script src="' . e(asset('js/print.js')) . '"></script>' : '';
        return '<!doctype html><html><head><meta charset="utf-8"><title>' . e($r['receipt_no']) . '</title><style>' . self::css($pdf) . '</style></head><body>' . self::html($r, $pdf) . $js . '</body></html>';
    }
}
