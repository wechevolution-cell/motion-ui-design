<?php
/* Template engine. A template = views + assets + a manifest of design tokens.
   Templates only READ shop data; switching template never touches the shop database. */

final class Theme
{
    private static array $manifests = [];

    public static function exists(string $code): bool
    {
        return preg_match('/^t\d{2}$/', $code) && is_file(APP . '/templates/' . $code . '/manifest.php');
    }

    public static function manifest(string $code): array
    {
        if (!self::exists($code)) {
            $code = 't01';
        }
        return self::$manifests[$code] ??= (require APP . '/templates/' . $code . '/manifest.php') + ['code' => $code];
    }

    /** Merge saved token values over manifest defaults; only valid colours survive. */
    public static function tokens(array $manifest, array $saved): array
    {
        $out = [];
        foreach ($manifest['tokens'] as $key => $def) {
            $out[$key] = Sanitizer::color($saved[$key] ?? null, $def['default']);
        }
        return $out;
    }

    public static function css(array $tokens): string
    {
        $css = ':root{';
        foreach ($tokens as $k => $v) {
            $css .= '--' . str_replace('_', '-', $k) . ':' . $v . ';';
        }
        return $css . '}';
    }

    public static function view(string $code, string $name): string
    {
        $f = APP . '/templates/' . $code . '/' . $name . '.php';
        if (!is_file($f)) {
            $f = APP . '/templates/t01/' . $name . '.php';
        }
        return $f;
    }

    /** Relative luminance contrast (WCAG) — used to warn admins about unreadable colour pairs. */
    public static function contrast(string $a, string $b): float
    {
        $l = function (string $hex) {
            $c = array_map(fn($x) => hexdec($x) / 255, str_split(ltrim($hex, '#'), 2));
            $c = array_map(fn($x) => $x <= 0.03928 ? $x / 12.92 : (($x + 0.055) / 1.055) ** 2.4, $c);
            return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
        };
        [$x, $y] = [$l($a), $l($b)];
        return (max($x, $y) + 0.05) / (min($x, $y) + 0.05);
    }
}
