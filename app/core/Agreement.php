<?php
declare(strict_types=1);

final class Agreement
{
    public const CLASSES = ['center', 'right', 'title', 'clause', 'small', 'sig', 'nobreak'];
    public const TAGS = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ol', 'ul', 'li', 'h1', 'h2', 'h3', 'h4', 'div', 'span', 'table', 'thead', 'tbody', 'tr', 'td', 'th', 'hr'];

    public static function placeholders(): array
    {
        return [
            'agreement_no' => 'Agreement reference number', 'tenancy_ref' => 'Tenancy reference', 'date_long' => 'Date of agreement, e.g. 3rd day of October 2026',
            'landlord_name' => 'Landlord full name', 'landlord_address' => 'Landlord address', 'tenant_name' => 'Tenant full name', 'tenant_address' => 'Tenant address',
            'property_title' => 'Property title', 'property_address' => 'Property address and area', 'property_ref' => 'Property reference',
            'rent_amount' => 'Rent amount in figures', 'rent_words' => 'Rent amount in words', 'rent_frequency_text' => 'e.g. per annum',
            'duration_text' => 'e.g. 1 year', 'start_date_long' => 'Start date, long form', 'end_date_long' => 'End date, long form',
            'start_date' => 'Start date (short)', 'end_date' => 'End date (short)', 'purpose' => 'Permitted use (e.g. residential)',
            'security_deposit' => 'Security / caution deposit', 'total_payable' => 'Total payable under the tenancy', 'company_name' => 'Agency name',
            'extra_terms' => 'Additional terms typed when generating', 'guarantor_block' => 'Guarantor paragraph (blank when no guarantor)', 'signature_block' => 'Signature boxes',
        ];
    }

    /** Keep only layout tags and a few whitelisted class names. Everything else, including scripts and attributes, is dropped. */
    public static function sanitize(string $html): string
    {
        $html = trim($html);
        if ($html === '') return '';
        $prev = libxml_use_internal_errors(true);
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->loadHTML('<?xml encoding="UTF-8"><body>' . $html . '</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $body = $doc->getElementsByTagName('body')->item(0);
        if (!$body) return '';
        self::clean($body);
        $out = '';
        foreach ($body->childNodes as $n) $out .= $doc->saveHTML($n);
        return trim($out);
    }
    private static function clean(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);
                if (!in_array($tag, self::TAGS, true)) {
                    if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'link', 'meta', 'svg', 'math'], true)) {
                        $node->removeChild($child);
                        continue;
                    }
                    self::clean($child);
                    while ($child->firstChild) $node->insertBefore($child->firstChild, $child);
                    $node->removeChild($child);
                    continue;
                }
                $keepClass = '';
                if ($child->hasAttribute('class')) {
                    $keepClass = implode(' ', array_intersect(preg_split('/\s+/', $child->getAttribute('class')) ?: [], self::CLASSES));
                }
                foreach (iterator_to_array($child->attributes) as $a) $child->removeAttribute($a->name);
                if ($keepClass !== '') $child->setAttribute('class', $keepClass);
                self::clean($child);
            } elseif (!($child instanceof DOMText)) {
                $node->removeChild($child);
            }
        }
    }

    public static function unknownPlaceholders(string $html): array
    {
        preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/', $html, $m);
        return array_values(array_diff(array_unique($m[1]), array_keys(self::placeholders())));
    }

    /** $vars values are inserted verbatim: plain values must be escaped by the caller, HTML blocks are trusted code-built markup. */
    public static function fill(string $tpl, array $vars): string
    {
        return preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', fn($m) => $vars[$m[1]] ?? '', $tpl) ?? $tpl;
    }

    public static function longDate(string $ymd): string
    {
        $d = new DateTimeImmutable($ymd);
        $n = (int)$d->format('j');
        $suf = ($n % 100 >= 11 && $n % 100 <= 13) ? 'th' : [1 => 'st', 2 => 'nd', 3 => 'rd'][$n % 10] ?? 'th';
        return $n . $suf . ' day of ' . $d->format('F Y');
    }
    public static function durationText(int $months): string
    {
        if ($months % 12 === 0) {
            $y = intdiv($months, 12);
            return $y . ' year' . ($y > 1 ? 's' : '');
        }
        return $months . ' month' . ($months > 1 ? 's' : '');
    }

    public static function signatureBlock(?string $guarantorName, string $landlord, string $tenant): string
    {
        $cell = fn(string $role, string $name) => '<td class="sig"><div class="line"></div><strong>' . e($role) . '</strong><br>' . e($name) . '<br><span class="small">Date: ______________</span></td>';
        $rows = $cell('LANDLORD', $landlord) . $cell('TENANT', $tenant);
        $row2 = ($guarantorName ? $cell('GUARANTOR', $guarantorName) : '') . $cell('WITNESS', 'Name: ______________________');
        return '<table class="sigtable nobreak"><tr>' . $rows . '</tr><tr>' . $row2 . '</tr></table>';
    }

    public static function css(): string
    {
        return 'body{font-family:"DejaVu Serif","DejaVu Sans",serif;font-size:10.5pt;line-height:1.5;color:#111}
          h1{font-size:16pt;margin:6pt 0}h2{font-size:13pt}h3{font-size:10.5pt;margin:12pt 0 4pt;text-transform:uppercase}
          p{margin:0 0 7pt;text-align:justify}ol{margin:0 0 8pt;padding-left:22pt}li{margin-bottom:4pt;text-align:justify}
          .center{text-align:center}.right{text-align:right}.small{font-size:8.5pt;color:#444}
          table.sigtable{width:100%;margin-top:26pt;border-collapse:collapse;page-break-inside:avoid}
          td.sig{width:50%;padding:0 14pt 26pt 0;vertical-align:bottom;font-size:9.5pt}.line{border-bottom:1px solid #000;height:34pt;margin-bottom:4pt}
          .letterhead{width:100%;border-bottom:2px solid #12263f;margin-bottom:10pt;padding-bottom:6pt}.letterhead td{vertical-align:middle}
          .lh-name{font-family:"DejaVu Sans",sans-serif;font-size:15pt;font-weight:bold;color:#12263f}.lh-sub{font-family:"DejaVu Sans",sans-serif;font-size:8pt;color:#333}
          .notice{margin-top:14pt;padding:6pt 8pt;border:1px solid #999;font-family:"DejaVu Sans",sans-serif;font-size:7.5pt;color:#333}';
    }

    /** Full printable document (letterhead + body + notice) from a stored snapshot. */
    public static function document(array $agr, bool $forPdf): string
    {
        $logo = $forPdf ? Pdf::logoDataUri() : logo_url();
        $lh = '<table class="letterhead"><tr>' . ($logo ? '<td style="width:60px"><img src="' . e($logo) . '" style="height:48px"></td>' : '')
            . '<td><div class="lh-name">' . e(setting('company_name')) . '</div><div class="lh-sub">' . e(setting('company_tagline')) . '<br>' . e(setting('company_address'))
            . '<br>Tel: ' . e(setting('company_phone')) . (setting('company_reg') ? ' &nbsp;|&nbsp; ' . e(setting('company_reg')) : '') . '</div></td></tr></table>';
        $notice = '<div class="notice"><strong>Notice:</strong> ' . e(setting('legal_disclaimer')) . (setting('agreement_footer') !== '' ? '<br>' . e(setting('agreement_footer')) : '')
            . '<br>Generated ' . e(fmt_datetime($agr['generated_at'])) . ' &middot; Reference ' . e($agr['ref']) . '</div>';
        return $lh . $agr['body_html'] . $notice;
    }
}
