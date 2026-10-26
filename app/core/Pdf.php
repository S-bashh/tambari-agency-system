<?php
declare(strict_types=1);

final class Pdf
{
    public static function render(string $html, string $paper = 'A4', string $orientation = 'portrait', bool $pageNumbers = false): string
    {
        require_once TEAMS_ROOT . '/vendor/autoload.php';
        $opt = new \Dompdf\Options();
        $opt->set('isRemoteEnabled', false);
        $opt->set('chroot', [TEAMS_ROOT]);
        $opt->set('defaultFont', 'DejaVu Sans');
        $opt->set('fontCache', TEAMS_ROOT . '/storage/tmp');
        $opt->set('tempDir', TEAMS_ROOT . '/storage/tmp');
        $opt->set('isPhpEnabled', false);
        $opt->set('isJavascriptEnabled', false);
        $dompdf = new \Dompdf\Dompdf($opt);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper($paper, $orientation);
        $dompdf->render();
        if ($pageNumbers) {
            $c = $dompdf->getCanvas();
            $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
            $c->page_text($c->get_width() / 2 - 30, $c->get_height() - 28, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 8, [0.4, 0.4, 0.4]);
        }
        return (string)$dompdf->output();
    }

    public static function send(string $pdf, string $filename, bool $inline): never
    {
        if (!str_starts_with($pdf, '%PDF')) {
            http_error(500, 'The PDF could not be created. You can use Print instead.');
        }
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', $filename) ?: 'document.pdf';
        header('Content-Type: application/pdf');
        header('Content-Length: ' . strlen($pdf));
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $safe . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        echo $pdf;
        exit;
    }

    /** Logo as data URI so Dompdf never needs file or network access. */
    public static function logoDataUri(): string
    {
        $f = setting('logo_file');
        $p = TEAMS_ROOT . '/storage/images/' . basename($f);
        if ($f === '' || !preg_match('/^logo_[a-f0-9]+\.png$/', $f) || !is_file($p)) return '';
        return 'data:image/png;base64,' . base64_encode((string)file_get_contents($p));
    }
}
