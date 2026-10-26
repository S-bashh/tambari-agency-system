<?php
declare(strict_types=1);

final class ReportsController extends Controller
{
    public function actionIndex(): void
    {
        Auth::require('reports.view');
        $groups = [];
        foreach (Reports::catalogue() as $key => $def) {
            if (!Reports::allowed($def)) continue;
            $groups[$def[0]][] = ['key' => $key, 'title' => $def[1], 'desc' => $def[2]];
        }
        $this->view('reports/index', ['groups' => $groups, 'pageTitle' => 'Reports', 'active' => 'reports']);
    }

    public function actionRun(): void
    {
        Auth::require('reports.view');
        $key = $this->str('report');
        $def = Reports::catalogue()[$key] ?? null;
        if (!$def) http_error(404, 'That report does not exist.');
        if (!Reports::allowed($def)) http_error(403, 'Your role does not have access to the data in this report.');
        $f = Reports::filterValues();
        [, $title, $desc, , $filters, $dateLabel, $fn] = $def;
        $export = $this->str('export');
        $run = $this->str('run') === '1' || $export !== '' || !$filters;   // show the filter form first; run when asked
        $result = $run ? Reports::$fn($f) : null;

        if ($result && in_array($export, ['csv', 'pdf'], true)) {
            Audit::log('export', 'reports', null, "Exported report \"$title\" as " . strtoupper($export) . ' (' . count($result['rows']) . ' rows)', array_filter($f));
            $this->{'export' . ucfirst($export)}($key, $title, $result, $f);
        }
        $agents = in_array('agent', $filters, true) ? db_all('SELECT id, full_name FROM users ORDER BY full_name') : [];
        $this->view('reports/run', ['key' => $key, 'title' => $title, 'desc' => $desc, 'filters' => $filters, 'dateLabel' => $dateLabel, 'f' => $f, 'result' => $result, 'agents' => $agents, 'ran' => $run,
            'pageTitle' => $title, 'active' => 'reports']);
    }

    private function exportCsv(string $key, string $title, array $res, array $f): never
    {
        $fn = 'report-' . $key . '-' . date('Ymd') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $fn . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");   // BOM so Excel opens UTF-8 (₦, names) correctly
        fputcsv($out, [setting('company_name') . ' - ' . $title]);
        fputcsv($out, ['Generated', date('Y-m-d H:i'), 'by', Auth::user()['full_name']]);
        $bits = [];
        foreach (array_filter($f) as $k => $v) $bits[] = "$k=$v";
        if ($bits) fputcsv($out, ['Filters', implode('; ', $bits)]);
        fputcsv($out, []);
        fputcsv($out, array_column($res['cols'], 'label'));
        foreach ($res['rows'] as $r) {
            $line = [];
            foreach ($res['cols'] as $c) $line[] = $c['kind'] === 'text' ? Reports::csvSafe(Reports::cell($r[$c['key']] ?? '', 'text', true)) : Reports::cell($r[$c['key']] ?? '', $c['kind'], true);
            fputcsv($out, $line);
        }
        if (!empty($res['totals'])) {
            $line = [];
            foreach ($res['cols'] as $c) $line[] = isset($res['totals'][$c['key']]) ? Reports::cell($res['totals'][$c['key']], $c['kind'] === 'text' ? 'text' : $c['kind'], true) : '';
            fputcsv($out, $line);
        }
        fclose($out);
        exit;
    }

    private function exportPdf(string $key, string $title, array $res, array $f): never
    {
        $rows = array_slice($res['rows'], 0, 3000);
        $h = '<table class="meta"><tr><td><div class="co">' . e(setting('company_name')) . '</div><div class="t">' . e($title) . '</div></td><td class="r">Generated ' . e(date('d/m/Y H:i')) . '<br>by ' . e(Auth::user()['full_name']) . '</td></tr></table>';
        $bits = [];
        foreach (array_filter($f) as $k => $v) $bits[] = e($k) . ': ' . e((string)$v);
        if ($bits) $h .= '<div class="flt">Filters: ' . implode(' &middot; ', $bits) . '</div>';
        $h .= '<table class="g"><thead><tr>';
        foreach ($res['cols'] as $c) $h .= '<th' . ($c['kind'] === 'text' ? '' : ' class="n"') . '>' . e($c['label']) . '</th>';
        $h .= '</tr></thead><tbody>';
        foreach ($rows as $r) {
            $h .= '<tr>';
            foreach ($res['cols'] as $c) $h .= '<td' . ($c['kind'] === 'text' ? '' : ' class="n"') . '>' . e(Reports::cell($r[$c['key']] ?? '', $c['kind'], false)) . '</td>';
            $h .= '</tr>';
        }
        if (!empty($res['totals'])) {
            $h .= '<tr class="tot">';
            foreach ($res['cols'] as $c) $h .= '<td' . ($c['kind'] === 'text' ? '' : ' class="n"') . '>' . (isset($res['totals'][$c['key']]) ? e(Reports::cell($res['totals'][$c['key']], $c['kind'], false)) : '') . '</td>';
            $h .= '</tr>';
        }
        $h .= '</tbody></table>';
        if (count($res['rows']) > 3000) $h .= '<p class="flt">Only the first 3,000 of ' . count($res['rows']) . ' rows are shown in the PDF. Use CSV for everything.</p>';
        if (!empty($res['note'])) $h .= '<p class="flt">' . e($res['note']) . '</p>';
        $css = '@page{size:A4 landscape;margin:12mm}body{font-family:"DejaVu Sans",sans-serif;font-size:7.6pt;color:#111}.co{font-size:13pt;font-weight:bold;color:#12263f}.t{font-size:10.5pt;margin-top:2px}
            .meta{width:100%;margin-bottom:6px;border-bottom:2px solid #12263f}.meta td{vertical-align:top;padding-bottom:5px}.r{text-align:right;font-size:7.5pt;color:#444}.flt{font-size:7.2pt;color:#444;margin:4px 0}
            table.g{width:100%;border-collapse:collapse}table.g th{background:#12263f;color:#fff;text-align:left;padding:3px 4px;font-size:7pt}table.g td{padding:2.5px 4px;border-bottom:1px solid #d5dbe5}
            .n{text-align:right}tr.tot td{font-weight:bold;border-top:2px solid #12263f;background:#eef2f8}';
        try {
            $pdf = Pdf::render('<!doctype html><html><head><meta charset="utf-8"><style>' . $css . '</style></head><body>' . $h . '</body></html>', 'A4', 'landscape', true);
        } catch (Throwable $e) {
            app_log('Report PDF failed: ' . $e->getMessage());
            http_error(500, 'The PDF could not be created. Try the CSV export instead.');
        }
        Pdf::send($pdf, 'report-' . $key . '-' . date('Ymd') . '.pdf', false);
    }
}
