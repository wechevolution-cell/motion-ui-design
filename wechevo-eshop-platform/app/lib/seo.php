<?php
/* SEO/AEO: meta tags, JSON-LD @graph, sitemap.xml, robots.txt, llms.txt. */

final class Seo
{
    public string $title = '';
    public string $description = '';
    public string $canonical = '';
    public string $robots = 'index, follow';
    public ?string $image = null;
    public string $type = 'website';
    public array $graph = [];
    public array $crumbs = [];

    public function __construct(public string $base, public array $company, public array $seoCfg) {}

    public function crumb(string $name, string $path): void
    {
        $this->crumbs[] = ['name' => $name, 'path' => $path];
    }

    public function head(): string
    {
        $h = '<title>' . e($this->title) . "</title>\n";
        if ($this->description) {
            $h .= '<meta name="description" content="' . e(mb_substr($this->description, 0, 300)) . "\">\n";
        }
        $h .= '<meta name="robots" content="' . e($this->robots) . "\">\n";
        if ($this->canonical) {
            $h .= '<link rel="canonical" href="' . e($this->canonical) . "\">\n";
            $h .= '<link rel="alternate" hreflang="el" href="' . e($this->canonical) . "\">\n";
        }
        $og = ['og:type' => $this->type, 'og:title' => $this->title, 'og:description' => $this->description, 'og:url' => $this->canonical, 'og:site_name' => $this->company['name'] ?? '', 'og:locale' => 'el_GR'];
        if ($this->image) {
            $og['og:image'] = $this->abs($this->image);
        }
        foreach ($og as $k => $v) {
            if ($v !== '' && $v !== null) {
                $h .= '<meta property="' . $k . '" content="' . e($v) . "\">\n";
            }
        }
        $h .= '<meta name="twitter:card" content="' . ($this->image ? 'summary_large_image' : 'summary') . "\">\n";
        if (!empty($this->seoCfg['gsc_verification'])) {
            $h .= '<meta name="google-site-verification" content="' . e($this->seoCfg['gsc_verification']) . "\">\n";
        }
        $h .= '<script type="application/ld+json">' . json_encode($this->jsonLd(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . "</script>\n";
        return $h;
    }

    public function abs(string $u): string
    {
        return str_starts_with($u, 'http') ? $u : $this->base . $u;
    }

    public function jsonLd(): array
    {
        $org = ['@type' => 'Organization', '@id' => $this->base . '/#organization', 'name' => $this->company['name'] ?? '', 'url' => $this->base . '/'];
        if (!empty($this->company['logo'])) {
            $org['logo'] = $this->abs($this->company['logo']);
        }
        if (!empty($this->company['email'])) {
            $org['email'] = $this->company['email'];
        }
        if (!empty($this->company['phone'])) {
            $org['telephone'] = $this->company['phone'];
        }
        $same = array_values(array_filter(array_merge(array_values($this->company['social'] ?? []), (array)($this->seoCfg['same_as'] ?? []))));
        if ($same) {
            $org['sameAs'] = $same;
        }
        $graph = [
            $org,
            ['@type' => 'WebSite', '@id' => $this->base . '/#website', 'url' => $this->base . '/', 'name' => $this->company['name'] ?? '', 'inLanguage' => 'el-GR',
                'publisher' => ['@id' => $this->base . '/#organization'],
                'potentialAction' => ['@type' => 'SearchAction', 'target' => $this->base . '/search/?q={search_term_string}', 'query-input' => 'required name=search_term_string']],
            ['@type' => 'WebPage', '@id' => $this->canonical . '#webpage', 'url' => $this->canonical, 'name' => $this->title, 'description' => $this->description,
                'isPartOf' => ['@id' => $this->base . '/#website'], 'inLanguage' => 'el-GR'],
        ];
        if ($this->crumbs) {
            $items = [['@type' => 'ListItem', 'position' => 1, 'name' => 'Αρχική', 'item' => $this->base . '/']];
            foreach ($this->crumbs as $i => $c) {
                $items[] = ['@type' => 'ListItem', 'position' => $i + 2, 'name' => $c['name'], 'item' => $this->abs($c['path'])];
            }
            $graph[] = ['@type' => 'BreadcrumbList', '@id' => $this->canonical . '#breadcrumb', 'itemListElement' => $items];
            $graph[2]['breadcrumb'] = ['@id' => $this->canonical . '#breadcrumb'];
        }
        return ['@context' => 'https://schema.org', '@graph' => array_merge($graph, $this->graph)];
    }

    /** Product + Offer: only real fields; no ratings/reviews are ever generated. */
    public function product(array $p, array $images, string $url, array $variants = []): void
    {
        $offerPrice = Catalog::effectivePrice($p);
        $node = [
            '@type' => 'Product', '@id' => $url . '#product', 'name' => $p['name'], 'url' => $url,
            'description' => Sanitizer::text($p['short_description'] ?: $p['description'], 500),
            'offers' => [
                '@type' => 'Offer', 'url' => $url, 'priceCurrency' => 'EUR', 'price' => number_format($offerPrice, 2, '.', ''),
                'availability' => Catalog::inStock($p) ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'itemCondition' => 'https://schema.org/NewCondition', 'seller' => ['@id' => $this->base . '/#organization'],
            ],
        ];
        if ($images) {
            $node['image'] = array_values(array_map(fn($i) => $this->abs($i['full']), array_slice($images, 0, 5)));
        }
        if ($p['sku']) $node['sku'] = $p['sku'];
        if ($p['barcode'] && preg_match('/^\d{8,14}$/', $p['barcode'])) $node['gtin'] = $p['barcode'];
        if ($p['mpn']) $node['mpn'] = $p['mpn'];
        if ($p['brand']) $node['brand'] = ['@type' => 'Brand', 'name' => $p['brand']];
        $this->graph[] = $node;
        $this->type = 'product';
    }

    public function itemList(array $products, callable $urlFor): void
    {
        $items = [];
        foreach (array_values($products) as $i => $p) {
            $items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'url' => $this->abs($urlFor($p)), 'name' => $p['name']];
        }
        if ($items) {
            $this->graph[] = ['@type' => 'ItemList', 'itemListElement' => $items, 'numberOfItems' => count($items)];
        }
    }

    public function faq(array $qa): void
    {
        $qa = array_values(array_filter($qa, fn($x) => !empty($x['q']) && !empty($x['a'])));
        if ($qa) {
            $this->graph[] = ['@type' => 'FAQPage', 'mainEntity' => array_map(fn($x) => ['@type' => 'Question', 'name' => $x['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $x['a']]], $qa)];
        }
    }

    /* ---------- crawl files ---------- */
    public static function robots(string $base): string
    {
        $bots = ['GPTBot', 'OAI-SearchBot', 'ChatGPT-User', 'ClaudeBot', 'Claude-Web', 'PerplexityBot', 'Google-Extended', 'Applebot-Extended'];
        $t = "User-agent: *\nDisallow: /admin/\nDisallow: /cart/\nDisallow: /checkout/\nDisallow: /order/\nDisallow: /search/\nDisallow: /wishlist/\nAllow: /\n\n";
        foreach ($bots as $b) {
            $t .= "User-agent: $b\nDisallow: /admin/\nDisallow: /checkout/\nAllow: /\n\n";
        }
        return $t . "Sitemap: $base/sitemap.xml\n";
    }

    public static function sitemap(PDO $db, string $base): string
    {
        $x = new XMLWriter();
        $x->openMemory();
        $x->startDocument('1.0', 'UTF-8');
        $x->startElement('urlset');
        $x->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $x->writeAttribute('xmlns:image', 'http://www.google.com/schemas/sitemap-image/1.1');
        $url = function (string $loc, ?string $mod = null, array $images = []) use ($x) {
            $x->startElement('url');
            $x->writeElement('loc', $loc);
            if ($mod) $x->writeElement('lastmod', substr($mod, 0, 10));
            foreach (array_slice($images, 0, 5) as $im) {
                $x->startElement('image:image');
                $x->writeElement('image:loc', $im);
                $x->endElement();
            }
            $x->endElement();
        };
        $url($base . '/');
        $url($base . '/products/');
        foreach (Catalog::categoryFlat($db) as $c) {
            $url($base . Catalog::categoryUrl($db, (int)$c['id']), $c['updated_at'] ?? $c['created_at']);
        }
        foreach (rows($db, "SELECT slug, updated_at FROM pages WHERE status = 'published'") as $p) {
            $url($base . '/' . $p['slug'] . '/', $p['updated_at']);
        }
        $last = 0;
        while ($batch = rows($db, "SELECT id, slug, updated_at, created_at FROM products WHERE status = 'active' AND deleted_at IS NULL AND ifnull(robots,'') NOT LIKE '%noindex%' AND id > ? ORDER BY id LIMIT 1000", [$last])) {
            $covers = Catalog::coverMap($db, array_column($batch, 'id'));
            foreach ($batch as $p) {
                $last = (int)$p['id'];
                $img = isset($covers[$p['id']]) ? [str_starts_with($covers[$p['id']]['full'], 'http') ? $covers[$p['id']]['full'] : $base . $covers[$p['id']]['full']] : [];
                $url($base . '/product/' . $p['slug'] . '/', $p['updated_at'] ?? $p['created_at'], $img);
            }
        }
        $x->endElement();
        return $x->outputMemory();
    }

    /** llms.txt: a plain summary for AI crawlers. */
    public static function llms(PDO $db, string $base, array $company, array $seoCfg): string
    {
        $t = '# ' . ($company['name'] ?? 'Κατάστημα') . "\n\n";
        $desc = $seoCfg['home_description'] ?? '';
        $t .= '> ' . ($desc ?: 'Ηλεκτρονικό κατάστημα στην Ελλάδα.') . "\n\n";
        $t .= "Γλώσσα: Ελληνικά. Νόμισμα: EUR (οι τιμές περιλαμβάνουν ΦΠΑ).\n";
        if (!empty($company['email'])) $t .= "Email: {$company['email']}\n";
        if (!empty($company['phone'])) $t .= "Τηλέφωνο: {$company['phone']}\n";
        if (!empty($company['address'])) $t .= "Διεύθυνση: {$company['address']}\n";
        $t .= "\n## Κατηγορίες\n\n";
        foreach (Catalog::categoryFlat($db) as $c) {
            $t .= str_repeat('  ', $c['depth']) . "- [{$c['name']}]($base" . Catalog::categoryUrl($db, (int)$c['id']) . ")\n";
        }
        $t .= "\n## Πληροφορίες\n\n";
        foreach (rows($db, "SELECT slug, title FROM pages WHERE status = 'published' ORDER BY sort") as $p) {
            $t .= "- [{$p['title']}]($base/{$p['slug']}/)\n";
        }
        $t .= "\n## Χρήσιμοι σύνδεσμοι\n\n- [Όλα τα προϊόντα]($base/products/)\n- [Sitemap]($base/sitemap.xml)\n";
        if (!empty($seoCfg['llms_extra'])) {
            $t .= "\n" . Sanitizer::text($seoCfg['llms_extra'], 5000) . "\n";
        }
        return $t;
    }
}
