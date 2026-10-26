<?php
declare(strict_types=1);

final class MediaController extends Controller
{
    public function actionImage(): void
    {
        Auth::require('properties.view');
        $name = $this->str('f');
        $thumb = $this->str('size') === 'thumb';
        if (!preg_match('/^[a-f0-9]{32}$/', $name) || !db_val('SELECT COUNT(*) FROM property_images WHERE stored_name = ?', [$name])) {
            http_response_code(404); exit;
        }
        $path = TEAMS_ROOT . '/storage/images/' . $name . ($thumb ? '_t' : '') . '.jpg';
        if (!is_file($path)) { http_response_code(404); exit; }
        header('Content-Type: image/jpeg');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, max-age=86400');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    /** Public: needed on the sign-in page. */
    public function actionLogo(): void
    {
        $f = setting('logo_file');
        $path = TEAMS_ROOT . '/storage/images/' . basename($f);
        if ($f === '' || !preg_match('/^logo_[a-f0-9]+\.png$/', $f) || !is_file($path)) { http_response_code(404); exit; }
        header('Content-Type: image/png');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: public, max-age=86400');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }
}
