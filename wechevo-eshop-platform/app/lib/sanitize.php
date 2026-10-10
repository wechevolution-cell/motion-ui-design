<?php
/* Allow-list HTML sanitizer for admin-entered rich text (descriptions, pages).
   Removes <script>, on* attributes, javascript:/data: URLs; iframes only from YouTube, Vimeo, Google Maps. */

final class Sanitizer
{
    private const TAGS = [
        'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [],
        'a' => ['href', 'title', 'target', 'rel'], 'ul' => [], 'ol' => [], 'li' => [],
        'h2' => ['id'], 'h3' => ['id'], 'h4' => ['id'], 'blockquote' => [], 'hr' => [], 'code' => [], 'pre' => [],
        'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => ['colspan', 'rowspan', 'scope'], 'td' => ['colspan', 'rowspan'],
        'img' => ['src', 'alt', 'width', 'height', 'loading'], 'figure' => [], 'figcaption' => [],
        'iframe' => ['src', 'width', 'height', 'allowfullscreen', 'title', 'loading'],
        'details' => [], 'summary' => [], 'span' => [],
    ];
    private const IFRAME_HOSTS = '#^https://(www\.)?(youtube\.com/embed/|youtube-nocookie\.com/embed/|player\.vimeo\.com/video/|google\.com/maps/embed)#i';

    public static function html(?string $html): string
    {
        $html = trim((string)$html);
        if ($html === '') {
            return '';
        }
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><div id="__root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        $root = $doc->getElementById('__root') ?: $doc->documentElement;
        self::clean($root);
        $out = '';
        foreach ($root->childNodes as $c) {
            $out .= $doc->saveHTML($c);
        }
        return $out;
    }

    private static function clean(DOMNode $node): void
    {
        for ($i = $node->childNodes->length - 1; $i >= 0; $i--) {
            $c = $node->childNodes->item($i);
            if ($c instanceof DOMComment || $c instanceof DOMProcessingInstruction) {
                $node->removeChild($c);
                continue;
            }
            if (!$c instanceof DOMElement) {
                continue;
            }
            $tag = strtolower($c->nodeName);
            if (in_array($tag, ['script', 'style', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'link', 'meta', 'base', 'svg', 'math'], true)) {
                $node->removeChild($c);
                continue;
            }
            if (!isset(self::TAGS[$tag])) {
                // unwrap unknown element, keep its children
                self::clean($c);
                while ($c->firstChild) {
                    $node->insertBefore($c->firstChild, $c);
                }
                $node->removeChild($c);
                continue;
            }
            foreach (iterator_to_array($c->attributes) as $attr) {
                $name = strtolower($attr->name);
                $val = trim($attr->value);
                $keep = in_array($name, self::TAGS[$tag], true);
                if ($keep && in_array($name, ['href', 'src'], true)) {
                    $keep = self::safeUrl($val, $tag === 'img');
                }
                if (!$keep) {
                    $c->removeAttribute($attr->name);
                }
            }
            if ($tag === 'iframe' && !preg_match(self::IFRAME_HOSTS, $c->getAttribute('src'))) {
                $node->removeChild($c);
                continue;
            }
            if ($tag === 'a' && $c->getAttribute('target') === '_blank') {
                $c->setAttribute('rel', 'noopener noreferrer');
            }
            if ($tag === 'img' && !$c->hasAttribute('loading')) {
                $c->setAttribute('loading', 'lazy');
            }
            self::clean($c);
        }
    }

    private static function safeUrl(string $u, bool $image): bool
    {
        $u = preg_replace('/[\x00-\x20]+/', '', html_entity_decode($u, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($u === '' || $u[0] === '/' || $u[0] === '#' || $u[0] === '?') {
            return true;
        }
        if (preg_match('#^(https?:|mailto:|tel:)#i', $u)) {
            return true;
        }
        // permit only small raster data images; never data:text/html, data:image/svg+xml, javascript:, vbscript:
        return $image && preg_match('#^data:image/(png|jpe?g|gif|webp);base64,#i', $u) && strlen($u) < 200000;
    }

    public static function text(?string $s, int $max = 1000): string
    {
        $s = trim(strip_tags((string)$s));
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $s);
        return mb_substr($s, 0, $max);
    }

    public static function color(?string $c, string $default): string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', (string)$c) ? strtoupper($c) : $default;
    }

    public static function url(?string $u): string
    {
        $u = trim((string)$u);
        if ($u === '') {
            return '';
        }
        if ($u[0] === '/' && ($u[1] ?? '') !== '/') {
            return $u;
        }
        return preg_match('#^https?://[^\s<>"]+$#i', $u) ? $u : '';
    }
}
