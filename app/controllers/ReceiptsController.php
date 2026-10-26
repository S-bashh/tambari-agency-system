<?php
declare(strict_types=1);

final class ReceiptsController extends Controller
{
    private function load(): array
    {
        Auth::require('payments.view');
        $r = Receipt::load($this->int('id'));
        if (!$r) http_error(404, 'That receipt could not be found.');
        return $r;
    }
    private function printed(array $r, string $how): void
    {
        db_exec('UPDATE receipts SET print_count = print_count + 1 WHERE id = ?', [(int)$r['receipt_id']]);
        $n = $r['print_count'] + 1;
        Audit::log($n > 1 ? 'reprint' : 'print', 'receipts', $r['receipt_id'], ($n > 1 ? 'Reprinted' : 'Printed') . " receipt {$r['receipt_no']} ($how, copy $n)");
    }

    public function actionView(): void
    {
        $r = $this->load();
        $this->view('receipts/view', ['r' => $r, 'pageTitle' => $r['receipt_no'], 'active' => 'payments']);
    }

    /** Standalone document used inside the preview frame and for printing. */
    public function actionRaw(): void
    {
        $r = $this->load();
        $auto = $this->int('print') === 1;
        if ($auto) $this->printed($r, 'print');
        header('Content-Type: text/html; charset=utf-8');
        echo Receipt::page($r, false, $auto);
        exit;
    }

    public function actionPdf(): void
    {
        $r = $this->load();
        try {
            $pdf = Pdf::render(Receipt::page($r, true), 'A5', 'landscape');
        } catch (Throwable $e) {
            app_log('Receipt PDF failed: ' . $e->getMessage());
            http_error(500, 'The PDF could not be created. You can use Print instead.');
        }
        $this->printed($r, 'PDF');
        Pdf::send($pdf, 'Receipt-' . $r['receipt_no'] . '.pdf', $this->int('inline') === 1);
    }
}
