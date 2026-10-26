<?php
declare(strict_types=1);

final class View
{
    /** Renderer parameters use a reserved prefix so a template variable such as $data or $tpl can never be shadowed. */
    public static function render(string $__tpl, array $__data = [], string|false $__layout = 'main'): void
    {
        $__data['pageTitle'] ??= '';
        $__data['active'] ??= '';
        extract($__data, EXTR_SKIP);
        ob_start();
        require TEAMS_ROOT . '/views/' . $__tpl . '.php';
        $content = ob_get_clean();
        if ($__layout === false) {
            echo $content;
            return;
        }
        require TEAMS_ROOT . '/views/layout/' . $__layout . '.php';
    }
    public static function partial(string $__tpl, array $__data = []): void
    {
        extract($__data, EXTR_SKIP);
        require TEAMS_ROOT . '/views/partials/' . $__tpl . '.php';
    }
}
