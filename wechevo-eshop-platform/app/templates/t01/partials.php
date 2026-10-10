<?php
/* Template 01 partials (pure functions: data in, HTML out). */

if (!function_exists('t01_img')) {
    function t01_img(?array $img, string $alt, string $sizes = '(max-width:860px) 50vw, 25vw', bool $eager = false, string $class = ''): string
    {
        if (!$img || ($img['src'] ?? '') === '') {
            $letter = mb_strtoupper(mb_substr(trim($alt) ?: '•', 0, 1));
            return '<div class="ph ' . e($class) . '" role="img" aria-label="' . e($alt) . '">' . e($letter) . '</div>';
        }
        return '<img src="' . e($img['src']) . '"' . ($img['srcset'] ? ' srcset="' . e($img['srcset']) . '" sizes="' . e($sizes) . '"' : '')
            . ' width="' . (int)$img['width'] . '" height="' . (int)$img['height'] . '" alt="' . e($img['alt'] ?: $alt) . '"'
            . ($eager ? ' fetchpriority="high"' : ' loading="lazy"') . ' decoding="async"' . ($class ? ' class="' . e($class) . '"' : '') . '>';
    }

    function t01_price(array $p, ?float $override = null): string
    {
        $final = $override ?? Catalog::effectivePrice($p);
        $was = $override === null && $final < (float)$p['price'] ? '<s>' . money($p['price']) . '</s>' : '';
        return '<span class="price">' . $was . money($final) . '</span>';
    }

    function t01_tint(int $id): string
    {
        $t = ['var(--secondary)', 'var(--accent)', 'var(--accent2)', 'var(--surface)'];
        return $t[$id % count($t)];
    }

    function t01_card(array $p, ?array $img, array $wish = []): string
    {
        $out = !Catalog::inStock($p);
        $sale = Catalog::effectivePrice($p) < (float)$p['price'];
        $badge = $out ? 'Εξαντλημένο' : ($sale ? '-' . round((1 - Catalog::effectivePrice($p) / (float)$p['price']) * 100) . '%' : '');
        $on = in_array((int)$p['id'], $wish, true);
        $url = '/product/' . e($p['slug']) . '/';
        return '<article class="pc' . ($out ? ' out' : '') . '" style="background:' . t01_tint((int)$p['id']) . '">'
            . '<div class="top">' . ($badge ? '<span class="bdg">' . e($badge) . '</span>' : '<span></span>')
            . '<form method="post" action="/wishlist/" data-wish><input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '"><input type="hidden" name="product_id" value="' . (int)$p['id'] . '"><input type="hidden" name="back" value="' . e(req_path()) . '">'
            . '<button class="heart" aria-pressed="' . ($on ? 'true' : 'false') . '" aria-label="Αγαπημένα: ' . e($p['name']) . '">' . ($on ? '♥' : '♡') . '</button></form></div>'
            . '<div class="im">' . t01_img($img, $p['name']) . '</div>'
            . '<h3><a href="' . $url . '">' . e($p['name']) . '</a></h3>'
            . '<div class="bot">' . t01_price($p) . '<a class="qv" href="' . $url . '" data-quick="' . $url . '?partial=quick">Γρήγορη ματιά</a></div>'
            . '</article>';
    }

    function t01_stock(array $p): array
    {
        if (!$p['manage_stock']) return ['in', 'Άμεσα διαθέσιμο'];
        $s = (int)$p['stock'];
        if ($s <= 0) return ['out', 'Εξαντλημένο'];
        if ($s <= 3) return ['low', "Τελευταία $s τεμάχια"];
        return ['in', 'Άμεσα διαθέσιμο'];
    }

    function t01_form_guard(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '"><input type="hidden" name="stamp" value="' . e(StoreRoutes::formStamp()) . '">'
            . '<div class="hp" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>';
    }
}
