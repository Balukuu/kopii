<?php
defined('KOPII_APP') or exit('Direct access denied.');

class View
{
    public static function render(string $template, array $data = [], string $layout = 'layouts/main'): void
    {
        extract($data, EXTR_SKIP);
        $templateFile = KOPII_ROOT . '/app/views/' . $template . '.php';
        if (!is_file($templateFile)) {
            throw new RuntimeException("View template not found: {$template}");
        }

        ob_start();
        require $templateFile;
        $content = ob_get_clean();

        clear_old_input();

        if ($layout === '') {
            echo $content;
            return;
        }

        $layoutFile = KOPII_ROOT . '/app/views/' . $layout . '.php';
        require $layoutFile;
    }

    public static function partial(string $partial, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        require KOPII_ROOT . '/app/views/partials/' . $partial . '.php';
    }
}
