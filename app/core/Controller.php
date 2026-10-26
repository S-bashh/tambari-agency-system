<?php
declare(strict_types=1);

abstract class Controller
{
    protected function view(string $tpl, array $data = [], string|false $layout = 'main'): void
    {
        View::render($tpl, $data, $layout);
    }
    protected function post(): void
    {
        if (!is_post()) {
            http_error(405, 'This action must be submitted from a form.');
        }
        if (!$_POST && !$_FILES && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            http_error(413, 'The files you sent are larger than this server allows (limit ' . ini_get('post_max_size') . ' per upload). Try fewer or smaller files.');
        }
        Csrf::verify();
    }
    protected function json(mixed $data, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
    protected function int(string $key, string $src = 'GET'): int
    {
        $a = $src === 'POST' ? $_POST : $_GET;
        return (int)($a[$key] ?? 0);
    }
    protected function str(string $key, string $src = 'GET'): string
    {
        $a = $src === 'POST' ? $_POST : $_GET;
        $v = $a[$key] ?? '';
        return is_array($v) ? '' : trim((string)$v);
    }
    protected function back(string $fallbackRoute, array $q = []): never
    {
        redirect($fallbackRoute, $q);
    }
}
