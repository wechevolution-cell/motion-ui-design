# ADDENDUM — As-built specification (Phase 1)

Append this to the original prompt. It records the decisions already taken and what was built, so a rebuild reproduces the same system without re-asking. Where it conflicts with the original prompt, **this addendum wins**.

---

## A. Decisions already made (do not ask again)

| Topic | Decision |
|---|---|
| Brand | Platform is a sub-product of **WeChevo**; no separate brand/logo. Storefront placeholder name: "Το Κατάστημά σας". |
| Code location | Own repository (name proposed: `wechevo-eshop-platform`). |
| Hosting topology | **One codebase, one install** on Hostinger. All domains point to the same folder. **One platform DB + one SQLite file per shop.** |
| Single domain | Must support **one domain for everything**: `/` storefront, `/admin/` shop dashboard, `/superadmin/` platform panel. Extra shops on their own domains also supported. |
| Languages | Storefronts: **Greek only**. Admin panels: **Greek only**. |
| Design scope of the 3 mockups | Storefront only; admin panels get a plain, functional design. |
| Chosen design | **Concept C — "Bento Market"** → Template 01. Concept B ("Swiss Clarity") also built as Template 02. |
| Payments (shop checkout) | Cash on delivery, bank transfer, card via **Viva Wallet or Stripe**. |
| Shipping | Flat rate + free above threshold + islands surcharge (per-shop settings). |
| Variants | Only the parent product counts toward the active-product quota; each variant has its own stock and optional price. |
| Import activation | New products import **inactive** by default; option "activate up to quota" (file order) or "from file column" (always within quota). |
| Downgrade | Super Admin confirms; system **auto-deactivates the most recently activated** products. Never deletes. |
| Grace period | **3 days** between EXPIRED and LOCKED (configurable). |
| Warnings | 10 / 3 / 1 days before expiry, each sent once (configurable list). |
| Not in scope | Blog, portfolio, WordPress / Rank Math import, before/after WebGL hero, 3D product viewers. |

---

## B. Technical architecture

- **PHP 8.1+ / SQLite / PDO**. No Composer, no npm, no build step in production. Tested on PHP 8.3.
- `index.php` front controller + `.htaccess` rewrite. Local run: `php -S localhost:8000 index.php` (shops on `*.localhost`).
- **Request routing by host**:
  1. Not installed → setup wizard.
  2. Platform host + path `/superadmin*` → Super Admin. Platform host with no shop mapped → Super Admin (`/` = small landing).
  3. Otherwise resolve shop by domain (`shop_domains`); `www.` and secondary domains 301 → primary; HTTPS forced except on `*.localhost`.
  4. `/admin*` → Shop Admin; everything else → Storefront.
- **Folders**: `app/` (lib, migrations, routes, views, templates) · `assets/` · `uploads/{shopId}/Y/m/` · `data/` (platform.sqlite, shops/{id}.sqlite, backups, logs, feeds, imports). `app/` and `data/` denied by .htaccess; `uploads/` has no PHP execution, nosniff, CSP sandbox; `.sqlite/.log/.md/.ini` blocked.
- **Migrations**: versioned SQL arrays (`PRAGMA user_version`), applied automatically on first open. DB pragmas: WAL, foreign_keys, busy_timeout.
- **Typed parameter binding** (SQLite compares INTEGER 0 ≠ TEXT '0'). A SQL function `fold()` gives accent/case-insensitive Greek search.
- **Business limits are data, never code**: prices, quotas, durations, grace/warning days live in DB tables/settings.
- `cron.php` (CLI or URL with HMAC key) every 15 min: status machine, notifications/e-mail, continue background imports, refresh feeds. Scheduler also runs lazily every 10 min from web traffic.
- `tools/build.sh` → `dist/wechevo-site-php.zip` (excludes data, uploads, tests).

## C. Data model

**Platform DB**: `settings`, `subscription_plans` (price, setup_fee, active_product_limit, stored_product_limit, staff_limit, storage_mb, duration_options JSON, features JSON, status), `templates`, `shops` (client data, hosting fields, plan_id, template_code, status, active/stored limit overrides, subscription_start/expires/duration, soft delete), `shop_domains` (domain, is_primary, ssl_status, dns_status), `users` (shop_id NULL = super admin; role; permissions JSON; lockout fields; must_change_password), `payment_providers`, `subscriptions`, `payments` (abstraction: provider → payment → subscription → shop), `subscription_history`, `notifications` (dedupe_key), `audit_logs`, `impersonation_tokens`, `rate_limits`.

**Shop DB (one per tenant)**: `settings` (company, theme, shipping, payments, homepage, popup, seo), `categories` (unlimited tree, unique slug per level), `products` (all spec fields + status active/inactive + activated_at + SEO + soft delete; indexes incl. NOCASE SKU), `product_categories`, `product_images` (media_id or remote url), `product_variants`, `product_attributes`, `media` (WebP variants JSON), `pages`, `menus`/`menu_items`, `customers`, `addresses`, `orders` (snapshot totals, invoice fields, payment/shipping status, access_token), `order_items` (snapshot), `order_notes`, `subscribers` (consent text, token), `contact_messages`, `feeds`, `feed_logs`, `imports`, `import_rows`, `import_logs`.

Seeded on install: plans Starter €20/20, Business €45/50, Pro €80/100, setup fee €150, stored limit 10,000, durations 1/3/6/12, staff 2/5/10; templates t01–t10 (t01, t02 available); payment providers. Seeded per shop: settings defaults, 7 pages (about, contact, shipping, returns, terms, privacy, cookies) marked "Κείμενο προτύπου — επαληθεύστε πριν τη δημοσίευση", 2 menus, 3 feeds (Skroutz, BestPrice inactive; generic active), optional demo catalogue with generated images.

## D. Business rules (must hold)

1. **Quota**: activation (single, bulk, import, product form) checked server-side inside `BEGIN IMMEDIATE`; bulk is all-or-nothing; clear Greek message: "Το πακέτο σας επιτρέπει έως N ενεργά προϊόντα και έχετε ήδη N ενεργά…". Limit = shop override ?? plan. Storage limit separate (default 10,000). Inactive products are never deleted to satisfy a quota.
2. **Status machine**: PENDING (future start) → ACTIVE → EXPIRING (≤ max warning days) → EXPIRED (past expiry, storefront still serves) → LOCKED (after grace). Also SUSPENDED, DELETED. Serving statuses: ACTIVE, EXPIRING, EXPIRED.
3. **Locked/suspended**: every public URL (storefront, feeds, sitemap, robots, checkout, admin, login) returns the **lock page, HTTP 503**, no data, no forms. Super Admin can still enter via impersonation.
4. **Renewal** touches only subscription fields/status; extends from current expiry if in future, else from now; logs history + optional payment.
5. **Frontend order of checks**: shop serving? → product active? Inactive/deleted product → 404. Orders keep snapshots, unaffected by deactivation/deletion.
6. **Template switch** changes presentation only; data identical before/after.
7. **Tenant isolation**: separate DB file per shop + session bound to shop id + user.shop_id check → cross-tenant access = 403.
8. Soft deletes for products, categories, shops, users. Shop delete = 2 steps (type domain), restore possible, permanent purge requires typed phrase; automatic backups before downgrade, delete, purge and every import.

## E. Super Admin (`/superadmin/`)

Setup wizard (no default credentials) · login with lockout (5 fails → 15 min) + IP rate limit · dashboard (total/active/locked/expiring shops, network active & stored products, MRR, unread notifications, shops by expiry) · shops list with filters · **7-step creation wizard** (client → domain → plan → duration → template → admin account → review; credentials shown once and optionally e-mailed) · shop page: renew/reset timer with payment record, change plan (downgrade confirmation), lock/suspend/unlock, re-check, manual backup, client & hosting fields, template, limit overrides, domains (add/primary/remove, DNS/SSL status), shop users + reset password, payments, subscription history, recent audit, danger zone · **impersonation** (one-time 2-minute token, banner, audited) · plans CRUD (limits, prices, durations, feature flags: import, export, feeds, popups, seo_advanced, staff, custom_domain) · templates (status; only implemented ones can be available) · system users · audit log with filters · notifications · settings (name, notify e-mail, grace days, warning days, force HTTPS, mail mode log/mail()/SMTP + test, cron command & URL) · backups (create, download) · own password.

## F. Shop Admin (`/admin/`)

Login per shop domain, forced password change on first login · banner for EXPIRING/EXPIRED/impersonation · **dashboard**: quota bar "ΕΝΕΡΓΑ ΠΡΟΪΟΝΤΑ: x / y", KPIs, 10-step onboarding checklist, recent orders · **products**: list (search incl. Greek folding, status/category/stock filters, sort, 25–200 per page, pagination for 10k), per-row toggle, bulk activate/deactivate/delete/assign category/price change (% , ±€, set)/export; edit form with all spec fields, multi-category, image upload (multi) + URLs, set main/remove image, up to N variant rows (2 options each, SKU/price/stock), attributes, SEO (slug, title, description, canonical, noindex), featured flag, status with quota check · categories tree CRUD (cycle protection) · orders (filters, detail, status/payment/shipping/tracking, notes, restock on cancel, notify customer) · customers & addresses · newsletter subscribers (consent, unsubscribe, CSV export) · contact messages · pages (template-text badge) · menus (category/page/URL, nesting, reorder) · media library (alt text) · **appearance**: design tokens with colour pickers, contrast warnings (<4.5:1), logo, favicon, live preview · **homepage CMS**: hero (text, CTA, image), promo, marquee, sections on/off, 3 value cards, FAQ, newsletter block · pop-up builder · feeds (generic/Skroutz/BestPrice/custom with category & stock filters and field selection, frequency, generate now) · **import/export** · SEO screen (home meta, GA4, Search Console, sameAs, llms extra text, catalogue report) · settings (company/social, shipping, payments incl. Stripe/Viva keys) · staff users (roles Owner/Administrator/Editor/Product Manager/Custom, 14 permission modules, plan staff limit, cannot grant more than you have) · own account.

## G. Import / export

CSV (auto delimiter, UTF-8 or Windows-1253) and XML (streaming XMLReader, `<product>`/`<item>`, Skroutz format). Flow: Upload → Validate (counts new/existing/duplicates/invalid) → Map columns (auto-guess Greek/English headers) → Options (create new, update by SKU NOCASE, activation mode) → Process in **batches of 250** via AJAX (resumable, cron continues) → Report (per-row errors, duplicates, quota notes, log). Category paths "A > B" auto-created. Clear message when file exceeds server upload limit. CSV export: all / active / inactive / category / selected.

## H. Storefront — Template 01 "Bento Market" (Concept C)

Deep ultramarine stage, colour-blocked bento tiles, Roboto Flex with animated width axis, floating pill header, **mobile bottom tab bar**, spring micro-motion, reduced-motion respected. Routes: `/`, `/products/`, `/category/a/b/`, `/product/slug/`, `/search/?q=`, `/cart/`, `/checkout/`, `/order/{number}/?k=token`, `/wishlist/`, `/contact/`, `/{page-slug}/`, `/newsletter/`, `/unsubscribe/`, `/sitemap.xml`, `/robots.txt`, `/llms.txt`, `/feed/{slug}.xml`. Trailing-slash 301.
Features: bento hero, marquee, category chips, product cards (badges, wishlist heart, quick-view dialog), listing with subcategory chips, in-stock filter, sort, pagination + "load more", product page (gallery + thumbs, variant selection with live stock/price, qty, sticky mobile buy bar, spec tiles, description, shipping info, related), **cart bottom sheet / side sheet** with free-shipping progress, single-page checkout (address, mainland/islands, receipt/invoice with VAT fields, payment method with fee, live totals, terms), order success (bank details for transfer), newsletter + pop-up (once per visitor per version), cookie banner (GA4 only after consent), 404 page.
**Template 02 "Swiss Clarity"** (Concept B): same views, own layout (search-first header + category bar) and stylesheet. Templates = manifest (design tokens + fonts) + views + assets; missing views fall back to t01.

## I. Commerce

Session cart; stock re-validated and decremented atomically at order time; customers upserted by e-mail; order number `YY#####`; e-mails to customer and merchant; payment abstraction (`PaymentGateway`): Stripe Checkout and Viva Smart Checkout (card shown only when keys exist; verification on return).

## J. SEO / AEO / GEO

Per-page title/description/canonical/robots, hreflang el, Open Graph, Twitter card, GSC meta; JSON-LD `@graph`: Organization, WebSite+SearchAction, WebPage, BreadcrumbList, Product+Offer (sku, gtin, mpn, brand, availability; **no fake ratings**), ItemList, FAQPage. Sitemap with images (active products only), robots.txt allowing GPTBot, OAI-SearchBot, ChatGPT-User, ClaudeBot, PerplexityBot, Google-Extended, Applebot-Extended and disallowing /admin, /superadmin, cart, checkout; llms.txt per shop. Sort/filter pages noindex.

## K. Security

password_hash, lockout, IP rate limits (login, checkout, forms), CSRF on every POST (419 page), session per area (`wc_sa`, `wc_adm`, `wc_s`; httponly, SameSite=Lax, secure on HTTPS, strict mode, regenerate on login), security headers, noindex on admin, allow-list HTML sanitizer (no script/on*/javascript:/risky data:, iframes only YouTube/Vimeo/Google Maps, images without safe src dropped), uploads validated by getimagesize + finfo, re-encoded, max 10 MB, max 2000 px, WebP sizes 480/960/1600, forms with honeypot + signed time stamp (≥3 s) + consent + PRG, audit log of logins, quota blocks, permission denials, tenant denials, impersonation, all shop/plan actions.

## L. Acceptance tests (`tests/acceptance.php`, 116 checks, must all pass on a fresh install)

Install & lockout & CSRF · §74 quota (100 imported → 20 active/80 inactive, 21st blocked, swap succeeds, forged status ignored) · storefront shows only active, sitemap/robots/llms/JSON-LD · §76 tenant isolation · §80 staff permissions + plan staff limit · purchase journey with variant, islands + COD totals, stock decrement, token-protected success, e-mail · forms anti-spam/consent · sanitizer & fake-image upload · §77 template switch with identical data hashes · §75/§79 expiry → lock (all URLs 503) → impersonation → renewal · downgrade auto-deactivation · §78 10,000-product XML (no duplicates, quota respected, re-import = updates via cron, feed, export, pagination) · single-domain mode · two-step delete. Plus browser QA at 1280/768/390: no JS errors, no horizontal overflow, no broken images, one h1 per page.

## M. Not built yet (Phase 2 backlog)

Templates t03–t10 · customer accounts (login, order history, saved addresses on storefront) · coupons · reviews (verified only) · redirect manager + 404 monitor · live SEO/AEO analyzer & keyword cannibalisation · drag-and-drop menu builder · downloading remote import images into media · client-facing expiry e-mails · storage-MB enforcement · AVIF (server GD lacks it) · live testing of Stripe/Viva with real credentials · Hostinger deployment verification (domain mapping, SMTP).

## N. Before going live

Domains → same folder + SSL · cron every 15 min · SMTP + test e-mail · confirm real plan prices/limits · replace all "Κείμενο προτύπου" texts, shipping costs, COD fee, bank IBAN per shop · test card payments in demo/test mode · upload limits ≥ 32M · no LiteSpeed full-page cache on shop domains · backup before uploading over an existing site.
