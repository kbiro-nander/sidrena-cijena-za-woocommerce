# Sidrena cijena za WooCommerce — implementation plan

## Context

Croatia's Government decisions of 10 September 2026 (NN 101/2026, docs 1212 and 1213, in force **1 October 2026**) oblige every retailer and service provider with a website to:

1. Show, next to every current price, the **anchor price** (*dodatna / sidrena cijena*): the regular price that applied on **10 September 2026** (or 2 May 2025 for shops that already displayed it for six FMCG categories). Clear, visible, legible, with the reference date, everywhere a price is shown.
2. Publish a **machine-readable price list** (.xml or .csv) on a fixed public URL, regenerated daily by 08:00 for goods and on every change for services, 30 days of versions retained, prescribed fields and file name (outlet form, address, mark, storage number, date+time).

Separately, the Omnibus rule (*Zakon o zaštiti potrošača* čl. 19) requires showing the **lowest price in the 30 days before a sale** with the discount % computed from it, and a permanent "bazna cijena" duty starts 17 November 2026 with a reference day still to be set by pravilnik.

The user wants a **distributable WordPress/WooCommerce plugin** for Croatian shops covering all three. The directory `/Users/krist/PhpstormProjects/sidrene-cijene` is empty (greenfield, no git yet). PHP 8.3 and Composer are installed; no wp-cli, no local WordPress.

### Decisions taken with the user
- Distributable: full settings page, hr_HR + en strings, no hard-coded store data.
- Anchor population: bulk "copy current regular price as anchor for date X", CSV import, per-product/variation manual fields, scheduled auto-snapshot on a future date.
- Omnibus 30-day lowest price included (history table + display + discount helper).
- Cart/checkout: classic PHP filters in v1; block cart/checkout only get anchor data via Store API (JS rendering in v2). Cheap block-cart fallback: `woocommerce_get_item_data` rows (rendered by both classic and block cart).
- Testing: PHPUnit + Brain Monkey only, no live site. PHPStan with WooCommerce stubs substitutes for live method-name checks.
- Architecture: modular PSR-4 plugin. Slug `sidrena-cijena-za-woocommerce`, namespace `SidrenaCijena`, prefix `scwc_`, PHP ≥ 8.1, WooCommerce ≥ 9.0, HPOS compatibility declared.
- Services: virtual products OR per-product "Ovo je usluga" checkbox (rule configurable). Products with empty price get a "cijena na upit" flag that excludes them from the list and is surfaced in an admin notice (law offers no guidance for quote-priced services).
- Per-category reference-date override (2025-05-02 FMCG case).
- Generic "reference price" registry (`anchor` now, `base` later) so bazna cijena is a config addition.

## Verified WooCommerce facts (from WC trunk source / developer docs)

| Item | Finding |
|---|---|
| GTIN | `WC_Product::get_global_unique_id()`, meta `_global_unique_id` (WC 9.2+). Guard with `method_exists`. |
| Brands | Core taxonomy `product_brand` since WC 9.6. Use `get_the_terms($id, 'product_brand')`; guard with `taxonomy_exists`. |
| Price HTML | `woocommerce_get_price_html ($price, $product)` fires for all product types incl. variable (after `woocommerce_variable_price_html`). |
| Variation JSON | `woocommerce_available_variation ($data, $variable, $variation)`; `price_html` already passes through the filter above. |
| Cart | `woocommerce_cart_item_price` and `woocommerce_cart_item_subtotal` (`$html, $cart_item, $key`); checkout review-order uses only the subtotal filter. |
| Admin fields | `woocommerce_product_options_pricing` (no args); `woocommerce_variation_options_pricing ($loop, $variation_data, $variation)`. |
| Save | `woocommerce_admin_process_product_object ($product)` and `woocommerce_admin_process_variation_object ($variation, $i)` fire BEFORE `save()` → use `update_meta_data()`, no second save. |
| Price change | `woocommerce_product_object_updated_props ($product, $updated_props)` fires for variations too. Scheduled sales (`wc_scheduled_sales`) DO call `save()`, so it fires with `price`/`date_on_sale_from` props (not `regular_price`). Listener must watch `price`, `regular_price`, `sale_price`, `date_on_sale_from`, `date_on_sale_to`. Daily sweep still needed for direct-SQL/ERP writes. |
| Store API | `woocommerce_store_api_register_endpoint_data([...])` on `woocommerce_blocks_loaded`; endpoint IDs from `CartItemSchema::IDENTIFIER` / `ProductSchema::IDENTIFIER` constants (never string literals; trunk value is `cart-item`). Output lands under `extensions.{namespace}`. |
| HPOS | `FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true)` on `before_woocommerce_init`. |
| Action Scheduler | `as_schedule_single_action($ts, $hook, $args, $group, $unique)`; cron-expression actions evaluate in UTC, so use a self-rescheduling single action computed with `wp_timezone()`. |
| Brain Monkey | `brain/monkey ^2.7`, `mockery/mockery ^1.6`, `phpunit/phpunit ^9.6` (PHPUnit 10+ unverified with Brain Monkey). |
| dbDelta | two spaces after `PRIMARY KEY`, no backticks, no `IF NOT EXISTS`, `$wpdb->get_charset_collate()`, version option gates upgrades. |

## Directory layout (as built)

```
sidrena-cijena-za-woocommerce.php   # header, requirements guard, HPOS/blocks compatibility, Plugin::instance()->boot()
uninstall.php                       # honours advanced.remove_data_on_uninstall (default false)
readme.txt  composer.json  phpunit.xml.dist  phpstan.neon.dist  phpstan-bootstrap.php  phpcs.xml.dist
bin/make-pot.sh  bin/build-zip.sh   # translation template, distributable zip (vendor/ without dev deps)
languages/sidrena-cijena-za-woocommerce.pot
assets/css/{frontend,admin}.css  assets/js/{admin-settings,admin-tools}.js
templates/price-badge.php  templates/cjenik-index.php  templates/admin/{category-overrides,tools}.php
src/
  Plugin.php  Container.php  functions.php
  Lifecycle/   Requirements, Activator, Deactivator, Upgrader
  Support/     Clock, WpClock, Money, DateFormat, Slugifier
  Settings/    Settings, Defaults, Sanitizer
  Reference/   ReferencePriceType, ReferencePriceRegistry, ReferencePrice, ReferencePriceRepository,
               ReferenceDateResolver, CategoryOverrideResolver, MissingReferenceCounter
  Product/     ProductSnapshot, ProductAdapter, MetaKeys, ServiceRule, UnitPrice, BrandResolver, BarcodeResolver
  Display/     BadgeContext, BadgeData, ReferenceView, OmnibusView, BadgeDataFactory, PriceFormatter, PriceBadge,
               PriceHtmlComposer, RenderGuard, RequestContext, PriceHtmlFilter, VariationJsonFilter, CartFilters,
               Shortcode, Assets
  History/     Schema, PriceRecord, PriceHistoryRepository, LowestPriceQuery, LowestPriceCalculator, LowestResult,
               Recorder, Transition, PriceChangeListener, OmnibusStateUpdater, DiscountCalculator, DailySweep,
               SweepResult, Pruner
  PriceList/   Outlet, Outlets, Item, ServiceItem, ItemFactory, Collector, FilenameBuilder, Writer, XmlWriter, CsvWriter,
               WriteStats, Storage, Manifest, Retention, Generator, GenerationResult
  Endpoint/    Endpoint, Headers, IndexRenderer, Response
  Scheduling/  SchedulerBackend, ActionSchedulerBackend, WpCronBackend, Scheduler, ServiceChangeDebouncer, JobRunner
  Notices/     AdminNotices, Environment
  Admin/       SettingsPage, Fields, FieldRenderer, ProductFields, VariationFields, ProductSave, ReferenceFieldLabel,
               AdminActions, StatusProvider
  Admin/Tools/ SnapshotRequest, SnapshotResult, SnapshotService, CsvImporter, CsvImportRow, ImportPreview,
               ImportResult, CsvExporter, ToolsAjax, ToolsPage
  StoreApi/    ExtendStoreApi
  Cli/         Commands
tests/         bootstrap.php, TestCase.php, Support/{FixedClock,FakeWpdb}.php, stubs/*.php, Unit/<Concern>/*Test.php
```

Rule: only `ProductAdapter`, repositories, `Storage`, `Endpoint`, scheduler backends and Admin pages touch WP/WC globals. All logic consumes `ProductSnapshot` DTOs and is unit-testable.

## Data model

**Product + variation meta** (same keys on both post types). Pattern `_scwc_ref_{key}_{suffix}`:

| Key | Meaning |
|---|---|
| `_scwc_ref_anchor_price` | reference price, `wc_format_decimal`, stored like `_regular_price` |
| `_scwc_ref_anchor_date` | `Y-m-d` per-product date override (optional) |
| `_scwc_ref_anchor_source` | `manual` / `snapshot` / `import` / `auto` |
| `_scwc_ref_anchor_na` | `1` = explicitly no reference (product introduced after the date) |
| `_scwc_ref_base_*` | same four for the second type, when enabled |
| `_scwc_is_service` | `yes` / `no` / absent |
| `_scwc_unit`, `_scwc_unit_quantity` | jedinica mjere + neto količina → unit price = price / qty |
| `_scwc_sale_name` | naziv posebnog oblika prodaje (fallback: global default) |
| `_scwc_exclude_from_price_list` | `yes` (also set by "cijena na upit") |
| `_scwc_omnibus_ref_price`, `_scwc_omnibus_sale_start`, `_scwc_omnibus_ref_source` | computed 30-day reference, frozen for the sale; source `history` / `regular_fallback` / `insufficient` |

Variations do not inherit `_scwc_ref_*` from parent unless `reference_prices.variation_inherit_parent`; unit/service/sale-name/exclude do inherit when absent.

**Options**: one autoloaded `scwc_settings` array with sections `outlet` (form default `webshop`, address, label, storage_number, merchant_name), `reference_prices` (per type: enabled, label, date, category_overrides `[{term_ids[], date}]`, auto_snapshot_at; plus `variation_inherit_parent`, `auto_na_after_date`), `display` (position, format `{label} {date}: {price}`, date_format `d. m. Y.`, loop/single/cart/checkout(unit|total|none)/mini_cart toggles, variable_loop range|none, omnibus on/label/replace_del/show_percent, load_css), `price_list` (enabled, formats, slug `cjenik`, generate_time `06:00`, regenerate_on_change services|all|never, debounce 300 s, retention_days ≥ 30 default 35, include_out_of_stock, include_hidden, service_rule, sale_name_default, csv_delimiter `;`, csv_bom, tax_mode incl, external_cron_key), `history` (enabled, retention 400 d, sweep_time `00:30`, omnibus_fallback regular|hide), `advanced` (remove_data_on_uninstall, debug_log). State options: `scwc_db_version`, `scwc_plugin_version`, `scwc_flush_rewrite`, `scwc_last_generation`, `scwc_services_dirty_at`.

**Table `{prefix}scwc_price_history`**: `id`, `product_id`, `parent_id`, `regular_price decimal(19,4)`, `sale_price`, `active_price NOT NULL`, `is_on_sale tinyint`, `source varchar(20)` (save|sweep|seed|snapshot|import|cli), `recorded_at datetime` UTC; keys `(product_id, recorded_at)`, `(recorded_at)`. Rows are change points only.

**Reference abstraction**: `ReferencePriceType {key, label, defaultDate, categoryOverrides, enabled, xmlElement, csvColumn}` with `metaKey($suffix)`. `ReferencePriceRegistry::fromSettings()` registers `anchor` always, `base` when enabled, then `apply_filters('scwc_reference_price_types')`. Date resolution order: variation meta → parent meta → category override (product's `product_cat` + ancestors, first match in settings order) → type default → `apply_filters('scwc_reference_date')`.

## Display rules (one renderer: `PriceBadge`)

| Case | Output appended to WC's price HTML |
|---|---|
| Simple/variation/external, anchor present | `<span class="scwc-ref-price" data-scwc-ref="anchor"><span class="scwc-ref-price__label">Cijena na dan 10. 9. 2026.:</span> <span class="scwc-ref-price__amount">14,99 €</span></span>` |
| On sale + omnibus enabled | if `replace_del` and lowest30 < regular, rebuild `<del>` via `wc_format_sale_price(lowest, current)`; then `<span class="scwc-lowest30">Najniža cijena u 30 dana prije sniženja: 12,99 €</span>` + optional `<span class="scwc-discount">−23 %</span>`; then anchor badge. Source `insufficient` → regular price if fallback=regular, else omit line (never show a wrong number). |
| Variable parent | range min–max over variations with a present reference; none → no badge. Selecting a variation swaps `price_html`, which already carries the variation badge. |
| Second type enabled | one badge per type in registry order |
| `_na` or empty | current price only, no placeholder |
| Grouped parent | no badge |
| Cart line | compact badge via `woocommerce_cart_item_price`; plus `woocommerce_get_item_data` row "Cijena na dan …" (works in block cart too) |
| Checkout review | `woocommerce_cart_item_subtotal` per `display.checkout` mode |
| Emails, admin, `wc/v3` REST | never (`RenderGuard`) |

Amounts go through `wc_get_price_to_display($product, ['price' => $amount])` + `wc_price` so tax mode matches; in cart context use `wc_get_price_including_tax`/`excluding_tax` per `WC()->cart->display_prices_including_tax()`. Theme API: `sidrena_cijena($product, $args)`, shortcode `[sidrena_cijena id key]`, template override `yourtheme/sidrena-cijena/price-badge.php`.

## Price history + Omnibus

- Record on `woocommerce_product_object_updated_props` (props above; simple/variation/external only), on new product/variation (seed), daily sweep at `history.sweep_time`, opportunistically during price-list generation, and an activation seed job. Row written only when regular/sale/active/is_on_sale changed vs. latest row.
- `LowestPriceQuery`: `MIN(active_price)` over rows in `[saleStart−30d, saleStart)` UNION the last row before the window (carry-in). Classification: `history`, `regular_fallback` (only a fresh seed row), `insufficient`.
- `OmnibusStateUpdater` transitions: `sale_started` → compute + store; `sale_lowered` → keep reference (progressive reduction rule); `sale_ended` → clear; on-sale with missing meta → self-heal.
- `DiscountCalculator::percent` = floor((ref−cur)/ref·100), null if ≤ 0.
- Per-variation only; no parent aggregation. `Pruner` keeps the latest row per product.

## Price list

**XML** (streamed via `XMLWriter` to temp file, atomic rename): root `<Cjenik verzija generirano izvor>` → `<ProdajniObjekt>` (Oblik, Adresa, Oznaka, BrojPohrane, Naziv, Url) → `<Proizvodi><Proizvod>` with `Naziv, Sifra, Marka, JedinicaMjere, CijenaZaJedinicuMjere, MaloprodajnaCijena, PosebniOblikProdaje (da|ne), NazivPosebnogOblikaProdaje, SidrenaCijena datum="…", NajnizaCijena30Dana (only on sale), Barkod, Dostupnost (dostupno|nedostupno), Url` → `<Usluge><Usluga>` with `NazivUsluge, MaloprodajnaCijena, PosebniOblikProdaje, NazivPosebnogOblikaProdaje, SidrenaCijena`. ASCII element names; `<BaznaCijena>` added when that type is enabled. Prices: dot, 2 dp, consumer (tax-inclusive) by default.

**CSV**: `fputcsv`, `;` default, UTF-8 + BOM; header `vrsta;naziv;šifra;marka;jedinica_mjere;cijena_za_jedinicu_mjere;maloprodajna_cijena;posebni_oblik_prodaje;naziv_posebnog_oblika_prodaje;sidrena_cijena;datum_sidrene_cijene;najniža_cijena_30_dana;barkod;dostupnost;url`. Filters `scwc_price_list_columns`, `scwc_price_list_item`.

**Outlets (v1.1)**: settings hold the primary outlet (`outlet.*`, the webshop) plus `outlets.additional` (poslovnice). `PriceList\Outlets::fromSettings()` yields all outlets with unique URL keys; the generator writes **one file per outlet per format** (identical rows, own `<ProdajniObjekt>` header), manifest entries carry `outlet`, retention keeps the newest file per (outlet, format), and the endpoint serves `/cjenik/{key}/latest.{xml,csv}`, `/cjenik/{key}/` and `/cjenik/{key}/index.json` in addition to the primary's `/cjenik/latest.*` (NN 101/2026 t. VI: each outlet needs its own file and 30-day archive).

**Filename**: `{oblik}_{adresa}_{oznaka}_{broj_pohrane}_{YYYYMMDD}_{HHMMSS}.{xml|csv}`, parts via `Slugifier` (remove_accents, lowercase, `[^a-z0-9]+`→`-`), site-local time. Example `webshop_ulica-1-10000-zagreb_web1_1_20261001_060012.xml`.

**Generator**: `Collector` pages `wc_get_products` (published, variations expanded, parents excluded, exclusions honoured), classifies via `ServiceRule`, builds items via `ItemFactory` (Brand/Barcode/UnitPrice/ReferencePriceRepository/OmnibusState); writers stream to `uploads/scwc-cjenik/tmp/` then rename; `Manifest` (`manifest.json`: files, type, generatedAt, counts, sha256, latest pointers); `Retention::prune` (≥ 30 d); `scwc_last_generation` updated; `do_action('scwc_price_list_generated')`. Transient lock against overlap.

**Endpoint**: rewrite rules `^{slug}/?$` → index HTML, `^{slug}/index\.json$`, `^{slug}/latest\.(xml|csv)$`, `^{slug}/([a-z0-9][a-z0-9._-]*\.(xml|csv))$`; query vars `scwc_cjenik`, `scwc_format`, `scwc_file`; `template_redirect` priority 1 streams with `readfile()`. Headers: `nocache_headers()` + `Cache-Control: no-cache` for index/latest, `public, max-age=86400, immutable` for named files; correct Content-Type, Content-Disposition inline, Content-Length, Last-Modified, ETag, `Access-Control-Allow-Origin: *`, `X-Robots-Tag: noindex` on data files, `DONOTCACHEPAGE`. 404 for names not in the manifest. Works with plain permalinks via `?scwc_cjenik=latest&scwc_format=xml`. `robots_txt` filter appends `Allow: /{slug}/`. Flush rewrite on activation and slug change.

**Scheduling**: Action Scheduler self-rescheduling single actions (`as_schedule_single_action($ts, 'scwc_generate_price_list', [], 'scwc', true)`), `$ts` computed from `generate_time` in `wp_timezone()` (today if future else tomorrow, so DST-correct). Same for `scwc_daily_sweep`, weekly `scwc_prune`, `scwc_auto_snapshot`. `WpCronBackend` fallback when AS functions are absent. Watchdog on `init` (hourly transient) calls `ensureScheduled()`; notice when last generation > 26 h old. External trigger `GET /{slug}/?scwc_run=1&key=…` + `wp scwc export` for hosts with `DISABLE_WP_CRON`.

**Service change**: `ServiceChangeDebouncer::touch()` schedules a unique single action `debounce_seconds` ahead; triggered from the price-change listener (services, or all products per setting), product create/trash/delete, snapshot/import completion.

## Admin

- **Settings page** (WooCommerce › Sidrena cijena), tabs: Prodajni objekt (with live filename preview) · Referentne cijene (per type: label, date, category-override repeater with "Dodaj FMCG kategorije 2. 5. 2025." helper, auto-snapshot datetime) · Prikaz (with live badge preview) · Cjenik (status box: last/next run, URLs, "Generiraj sada") · Povijest cijena · Napredno.
- **Product edit**: per-type price/date/"Nema referentne cijene" in the pricing group; general tab: Ovo je usluga, jedinica mjere, neto količina, naziv posebnog oblika prodaje, Isključi iz cjenika, Cijena na upit; read-only Omnibus panel. Variations: same reference fields per panel (`scwc_ref_{key}_price[$loop]`).
- **Tools page** (batched AJAX with progress, `manage_woocommerce` + nonce): Snapshot (type, date, only-missing/overwrite, skip-on-sale, dry-run) · CSV import (delimiter auto-detect, BOM strip, header aliases sku|šifra, price|cijena|sidrena_cijena, date|datum, key|tip, decimal comma, per-row errors, dry-run) · Export anchors CSV · Regenerate · Sweep · Reschedule.
- **Notices**: outlet data missing while list enabled (non-dismissible); schedule missing or stale > 26 h; last generation error; products without anchor count (transient 12 h); block cart/checkout detected (badge not rendered there); plain permalinks; WC < 9.6 (no brands) / < 9.2 (no GTIN); quote-priced services excluded.
- **WP-CLI** `wp scwc snapshot|export|import|sweep|schedule status|reset|prune|history`.

## Testing strategy

`tests/bootstrap.php` loads Composer autoload, WC class stubs (`WC_Product` family with `get_*('edit')` from a props array, `is_on_sale`, `get_visible_children`, `get_meta`, `update_meta_data`, `WC_DateTime`) and function stubs wrapped in `function_exists` (overridable per test with `Functions\when()`). `TestCase` does `Monkey\setUp/tearDown`, `MockeryPHPUnitIntegration`, `FixedClock`.

Pure unit tests: `ReferenceDateResolver`, `CategoryOverrideResolver` (ancestors provider injected), `ReferencePriceRegistry`, `Sanitizer`, `ServiceRule`, `UnitPrice`, `DiscountCalculator`, `LowestPriceQuery`, `LowestPriceCalculator`, `Recorder`, `OmnibusStateUpdater`, `FilenameBuilder`, `Slugifier`, `DateFormat`, `XmlWriter`/`CsvWriter` (golden output to memory stream), `ItemFactory`, `CsvImporter::parse`, `CsvExporter`, `ProductSave::extract`, `PriceBadge` + `BadgeDataFactory` (`wc_price` stubbed deterministically), `Scheduler::nextRunTimestamp` (DST cases with `Europe/Zagreb`), `Headers` (`Functions\expect('header')`), `Retention`/`Manifest` (temp dir). Hook-registration tests with `Actions\expectAdded`. `$wpdb` via Mockery. PHPStan level 6 with WooCommerce stubs on every milestone.

Representative test names: `ReferenceDateResolverTest::test_product_override_beats_category_override`, `::test_category_override_matches_ancestor_category`; `PriceBadgeTest::test_on_sale_replaces_del_with_lowest_30_when_lower_than_regular`, `::test_no_badge_when_reference_missing_or_na`, `::test_variable_range_uses_min_max_of_present_variations_only`; `LowestPriceQueryTest::test_includes_carry_in_row_before_window`; `OmnibusStateUpdaterTest::test_progressive_reduction_does_not_recompute_reference`; `FilenameBuilderTest::test_transliterates_croatian_diacritics`; `XmlWriterTest::test_escapes_ampersand_and_angle_brackets_in_names`; `CsvImporterTest::test_strips_bom_and_maps_croatian_headers`; `SnapshotServiceTest::test_copies_regular_not_sale_price`, `::test_marks_na_when_created_after_reference_date`; `SchedulerTest::test_dst_transition_keeps_local_time`; `HeadersTest::test_rejects_path_traversal`.

## Milestones (TDD per unit; each ends with `composer test` + `phpstan` green)

0. `git init`; write the design spec to `docs/superpowers/specs/2026-09-18-sidrena-cijena-design.md` (this plan's content) and commit.
1. **Skeleton & tooling**: main file, composer/PSR-4, phpunit + Brain Monkey bootstrap + WC stubs, PHPStan + stubs, PHPCS, `Requirements`, HPOS declare, text domain, `Plugin`, Activator/Deactivator/Upgrader, `Schema::install` (dbDelta).
2. **Reference model**: types/registry/repository/VO, date resolvers, `Settings`/`Defaults`/`Sanitizer`, `ProductSnapshot`/`ProductAdapter`, `ServiceRule`.
3. **Admin fields & settings page**: `SettingsPage` tabs, `ProductFields`, `VariationFields`, `ProductSave`.
4. **Display**: badge data/renderer/template, `RenderGuard`, price-html/variation/cart/item-data filters, shortcode, template functions, CSS.
5. **History + Omnibus**: repository, recorder, listener, query/calculator, state updater, discount, sweep, pruner, seed; badge integration.
6. **Bulk tools**: snapshot, CSV import/export, Ajax + tools page + JS, auto-snapshot job, CLI snapshot/import/sweep/history.
7. **Price list generation**: outlet/items/factory/collector, XML + CSV writers, filename builder, storage/manifest/retention, generator, CLI export.
8. **Endpoint & scheduling**: endpoint, headers, index renderer, robots, scheduler (AS + WP-Cron fallback), watchdog, debouncer, external trigger, notices, status boxes, CLI schedule/prune.
9. **Store API, i18n, packaging**: `ExtendStoreApi`, `.pot` + hr_HR, `readme.txt`, `uninstall.php`, zip build script; readme guidance on caching plugins, real cron, block cart limitation, theme overrides.

Milestones 4 and 7 are independent after 2; 5 precedes 7 so the list can include the 30-day lowest.

## Verification

- `composer test` (PHPUnit) and `composer phpstan` pass at every milestone; `composer phpcs` clean before packaging.
- Golden-file assertions for XML/CSV output against the NN 101/2026 field list (products: naziv, šifra, marka, jedinica mjere, cijena/jm, maloprodajna cijena + posebni oblik flag/name, sidrena cijena, barkod, dostupnost; services: naziv usluge, maloprodajna cijena + flag/name, sidrena cijena).
- `php -l` over all files and a smoke script that `require`s the main file with WP/WC function stubs to catch fatals at load.
- Final manual checklist in the plan against the legal requirements (display everywhere, date shown, daily 08:00, 30-day retention, filename parts, no login/bot barrier, 30-day lowest during sales, discount % from it).
- No live WordPress in scope (user decision). Note in readme that install on a staging site is recommended before 1 Oct 2026.

## Risks / open items carried into implementation

- Block cart/checkout render only the `item_data` row and Store API data in v1; admin notice states this.
- Themes that bypass `woocommerce_get_price_html` need the `sidrena_cijena()` template function (documented).
- History bootstrap: sales starting within 30 days of install get `regular_fallback` (flagged in admin); historical-lowest CSV import deferred to v1.1.
- Bazna cijena XML/CSV naming (`BaznaCijena`) is a placeholder until the pravilnik is published.
- Timezone without `timezone_string` (manual UTC offset) lacks DST; documented.
