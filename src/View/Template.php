<?php

namespace Carbon\Pay\View;

final class Template
{
    /**
     * Renders templates/{name}.php. A theme can override it with
     * {theme}/carbon-pay/{name}.php.
     */
    public static function render(string $name, array $vars = []): string
    {
        $file = self::locate($name);
        if ($file === null) {
            return '';
        }

        ob_start();
        (static function () use ($file, $vars) {
            extract($vars, EXTR_SKIP);
            include $file;
        })();

        return (string) ob_get_clean();
    }

    private static function locate(string $name): ?string
    {
        $name = preg_replace('/[^a-z0-9\-\/]/', '', $name);

        if (function_exists('locate_template')) {
            $themed = locate_template('carbon-pay/' . $name . '.php');
            if ($themed) {
                return $themed;
            }
        }

        $file = dirname(__DIR__, 2) . '/templates/' . $name . '.php';

        return is_readable($file) ? $file : null;
    }
}
