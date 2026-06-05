# WSN Profile Sync Architecture

**Last updated:** 2026-06-04 — transport destination corrected to direct WooPay host (Option C); the prior wpcom/v2/sites/{blog_id}/wcpay/wsn/profile path had no receiver and was never reaching WooPay.
How WSN Profile data flows from each WCPay merchant site to a new WooPay-server table keyed by `blog_id`, optimized for read-heavy shopper-facing access. Owned by RSM-3945 (Profile emitter) and the WooPay-side companion work (table + controller + storefront read-path merge).

This doc supersedes the contradicted parts of WooPay's `docs/wsn/merchant-appearance-sync.md` (which has both a `wp_options`-per-blog and a `host`-keyed-custom-table version) and resolves the open questions flagged in `.claude/tmp/artifacts/RSM-3930/plan.md`.

## Purpose

The WSN shopper-facing surface at `pay.woo.com/shop/<host>` needs per-merchant branding (logo, hero, shop name, tagline, refund policy, contact email, shipping promise, Stripe Elements appearance) to render anything beyond mock data. That data is **edited on the merchant's WCPay site** (Shopping Network Hub → Profile tab) and **consumed on the WooPay server** (storefront render). This doc defines the pipe between those two points.

## Current data location (WCPay merchant-side, today)

Nothing exists on WooPay yet — storefront serves mock JSON from `src/Shop/data/shops.json`.

**Storage on the merchant site** ([includes/wsn/class-wsn-settings.php](../../includes/wsn/class-wsn-settings.php)): 5 per-key `wp_options` under the `wcpay_wsn_*` prefix, all `autoload=false`, strict unset-as-default semantics:

| Key | Type | Meaning |
|---|---|---|
| `wcpay_wsn_enabled` | bool-as-string `'0'\|'1'` | Master toggle (merchant's opt-in) |
| `wcpay_wsn_hero_image_id` | int\|null | Hero banner attachment ID |
| `wcpay_wsn_logo_override_id` | int\|null | Logo override attachment ID (null = use site logo) |
| `wcpay_wsn_contact_email` | string\|''\|null | Three-state: null = use WC default, '' = explicit empty, string = override |
| `wcpay_wsn_refund_page_id` | int\|null | Selected refund-policy page ID |

**Product visibility / catalog exposure is NOT a WSN concern** — it's handled by WC's native product-catalog-visibility settings. There used to be a dedicated Visibility tab in the Hub with three modes (all / taxonomy / specific) backed by `wcpay_wsn_visibility_*` options, but it was removed 2026-06-05 in favor of relying on WC's existing surface. The ES indexer can read WC core's `_visibility` meta / `product_visibility` taxonomy directly — no WSN-specific opt-in per product, and no Jetpack Sync option whitelist for visibility (RSM-3946 was never wired).

**Server-side derivations** ([includes/admin/class-wc-rest-payments-wsn-settings-controller.php:119-214](../../includes/admin/class-wc-rest-payments-wsn-settings-controller.php#L119-L214)) are computed at GET time, not stored:

- `logo_url` — composed: `override → custom_logo (theme_mod) → site_logo (option) → site_icon → null`
- `default_logo_url`, `default_logo_source` — the fallback URL + which source produced it (`'site_logo' | 'site_icon' | 'none'`)
- `hero_image_url`
- `shop_name`, `tagline` — `get_bloginfo('name')`, `get_bloginfo('description')`
- `default_contact_email` — `woocommerce_email_reply_to_address` when `..._reply_to_name` is set, else `woocommerce_email_from_address`
- `shipping_regions` — names from `WC_Shipping_Zones::get_zones()`
- `free_shipping` — `WSN_Free_Shipping_Summarizer::summarize()` output (zones[] + human_summary)
- `refund_page_label`, `refund_page_url` — from the picked page ID via `get_the_title` + `get_permalink`
- `theme_type` — `'block' | 'classic'` (FSE detection)
- `logo_source` — current state: `'override' | 'site_logo' | 'site_icon' | 'none'`

**Appearance** (separate, comes from a different cache layer): the Stripe Elements appearance JSON via `WC_Payments_Styles_Cache::get_woopay_appearance()` at [includes/class-wc-payments-styles-cache.php:69](../../includes/class-wc-payments-styles-cache.php#L69). Null for classic themes until a shopper triggers checkout DOM extraction.

**Change signals** (what the emitter listens for):

- `wcpay_wsn_profile_changed` — fires on PUT `/wsn/settings` when any of the 4 PROFILE_FIELDS (`hero_image_id`, `logo_override_id`, `contact_email`, `refund_page_id`) changes. **Already exists** in the controller.
- `wcpay_woopay_appearance_changed` — fires inside `WC_Payments_Styles_Cache::set_woopay_appearance()` when the appearance/font_rules hash changes. **Does NOT exist yet** — added by RSM-3945.
- Recurring Action Scheduler — 6h backstop with jittered delay, catches missed hooks.

Composed payload is ~5–20 KB serialized.

## Architecture overview

```
┌──────────────────── WCPay merchant site ────────────────────┐
│                                                              │
│  Hub Profile UI                                              │
│       │                                                      │
│       ▼  (PUT /wsn/settings)                                │
│  WC_REST_Payments_WSN_Settings_Controller                    │
│       │  do_action('wcpay_wsn_profile_changed')             │
│       ▼                                                      │
│  WSN_Profile_Emitter::on_change_hook                         │
│       │  Action Scheduler: schedule_single_action(+60s)     │
│       ▼  (coalesces N rapid changes into 1 push)            │
│  WSN_Profile_Payload_Composer::compose()                     │
│       │  Reads WSN_Settings + compute_derivations()         │
│       │  + WC_Payments_Styles_Cache appearance              │
│       │  Strips street_address/postcode (privacy)           │
│       │  Hashes payload → version                           │
│       ▼                                                      │
│  WSN_Profile_Emitter::execute_push()                         │
│       │  Skip-emit if version === last_synced_version       │
│       │  WSN_Profile_Transport::send($payload)              │
│       │  → \Automattic\Jetpack\Connection\Client            │
│       │       ::remote_request($args, $body)                │
│       ▼  Jetpack-signed POST (X_JETPACK blog token)         │
└───────┼──────────────────────────────────────────────────────┘
        │
        │  https://pay.woo.com/wp-json/wsn/v1/merchants/{blog_id}/profile
        │  (host honors PLATFORM_CHECKOUT_HOST in non-prod environments)
        ▼
┌──────────────────── pay.woo.com (WPCOM Simple) ─────────────┐
│                                                              │
│  WsnMerchantProfileController                                │
│       │  permission_callback validates the X_JETPACK         │
│       │  blog-token signature via                            │
│       │  Jetpack_Server_Version::get_token_from_authorization_header │
│       │  Asserts path blog_id == signed blog_id (WOOPAY-454) │
│       │  Schema-validates (rejects address PII)             │
│       │  Conflict-resolve: version OR client_updated_at     │
│       ▼                                                      │
│  WsnMerchantProfileDataStore::upsert($blog_id, $payload)     │
│       │  INSERT ... ON DUPLICATE KEY UPDATE                  │
│       │  wp_cache_delete('wsn_merchant_profile:'.$blog_id)  │
│       ▼                                                      │
│  wp_wsn_merchant_profile (custom InnoDB table, PK blog_id)   │
│       │                                                      │
│       │  (read path — Option B, LOCKED 2026-05-31)           │
│       ▼                                                      │
│  /wsn/v1/stores/{host} handler                               │
│       │  1. Read ES store doc (EsSource::project_store_full) │
│       │     unchanged — projects basic store fields          │
│       │  2. Read WSN Profile table by blog_id                │
│       │     L1: wp_cache_get → DataStore::get_by_blog_id     │
│       │     Lazy-fetch fallback on cache+DB miss             │
│       │  3. Merge Profile fields onto the store object       │
│       │     (logo_url, description, contact_email,           │
│       │      return_policy, shipping_promise, …)             │
│       ▼                                                      │
│  marketplaceStoreByUrlToStorefront — store.* → shop.*        │
│       │  (unchanged adapter)                                 │
│       ▼                                                      │
│  Storefront render (Photon-proxied logo/hero, inline thumb)  │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

> **Architecture note — Option B (LOCKED 2026-05-31):** The Profile pipeline does NOT go through the ES `woo-product-catalog` index. ES handles product-catalog indexing using WC's native catalog-visibility signals (see "Product visibility" above — there is no WSN-specific opt-in per merchant). Profile data flows via outbound POST → WooPay-server table → handler-side merge onto the ES store object. This is "Option B" in the handoff at `.claude/tmp/artifacts/wsn-profile-storefront-handoff.md`. The earlier draft of this doc described an EsSource-side merge — that path was rejected; the storefront `/wsn/v1/stores/{host}` handler does the merge instead. ES projection stays simple; the WSN Profile read path is bolted on at the handler layer.

## Sync flow

```
1. TRIGGER (3 sources on the merchant site)
   ├── wcpay_wsn_profile_changed       (Hub PUT)
   ├── wcpay_woopay_appearance_changed (theme/style change — NEW hook in styles cache)
   └── wcpay_wsn_profile_sync_backstop (6h recurring AS action, jittered 10-60s)

2. DEBOUNCE (1-minute coalesce window)
   └── WSN_Profile_Emitter::on_change_hook
         → as_unschedule_action('wcpay_wsn_profile_sync')
         → as_schedule_single_action(time()+60, 'wcpay_wsn_profile_sync')
       N rapid changes within 60s → 1 push 60s after the last one.
       Mirrors Compatibility_Service's pattern (2 min there; we use 1 min for fresher
       storefront updates — write rate is sparse so cost is negligible).

3. COMPOSE (when the AS action fires)
   WSN_Profile_Payload_Composer::compose() returns:
     {
       schema_version: 1,
       payload_version: sha256(serialized payload),
       client_updated_at: gmdate('Y-m-d H:i:s'),
       blog_id: <recoverable from Jetpack but included for completeness>,
       host: parse_url(home_url(), PHP_URL_HOST),
       settings: WSN_Settings::get_all(),
       derivations: {
         // Canonical WCPay-side names — see "Storefront contract
         // reconciliation" below. WooPay's `/wsn/v1/stores/{host}`
         // handler does the rename at merge time so WCPay stays
         // decoupled from WooPay UI iteration.
         shop_name: ...,
         tagline: ...,               // → store.description (rename on WooPay side)
         logo_url: ...,              // → store.logo_url (passthrough; closes WOOPAY-458)
         logo_attachment_id: ...,    // for downstream metadata lookups
         default_logo_url, default_logo_source, logo_source,
         hero_image_url: ...,
         default_contact_email: ...,
         shipping_regions: ...,      // []
         free_shipping: {            // full object; WooPay flattens human_summary → store.shipping_promise at merge
           human_summary, has_free_shipping, zones[]
         },
         refund_page_url: ...,       // separate fields; WooPay bundles → store.return_policy{url,label} at merge
         refund_page_label: ...,
         theme_type: 'block' | 'classic',
       },
       appearance: <stripe Elements appearance> | null,
       font_rules: [...] | null,
       logo_dimensions: { width, height } | null,  // for the wordmark-swap aspect-ratio gate
       logo_thumb_b64: <≤8KB base64 thumbnail> | null,
       location: { country, region, city },        // NO street, NO postcode
       payload_version: sha256(serialized payload before this field),
     }

4. SKIP-EMIT GUARD
   if payload_version === get_option('wsn_profile_last_synced_version'):
       log("skipped: unchanged"); return

5. EMIT (direct Jetpack-signed POST to WooPay; throws on failure for emitter catch)
   try {
     WSN_Profile_Transport::send($payload)
     // → \Automattic\Jetpack\Connection\Client::remote_request($args, $body)
     // URL: https://pay.woo.com/wp-json/wsn/v1/merchants/{blog_id}/profile
     //      (host honors PLATFORM_CHECKOUT_HOST in non-prod)
     // Method: POST | Body: wp_json_encode($payload)
     // Signing: full X_JETPACK blog-token signature attached by Client
     //
     // This is NOT routed through WC_Payments_API_Client — that client
     // always prefixes URLs with public-api.wordpress.com/wpcom/v2/sites/{blog_id}/wcpay/
     // which targets the WCPay backend (Transact-API), not WooPay.
     // Mirrors the production-proven /init pattern at
     // includes/woopay/class-woopay-session.php::ajax_init_woopay.
   } catch ( Throwable $e ) {
     set_transient('wsn_profile_last_error',
       { message, http_status, ts }, 7 * DAY_IN_SECONDS);
   }
   if 2xx:
     update_option('wsn_profile_last_synced', time(), false);
     update_option('wsn_profile_last_synced_version', $payload_version, false);
     delete_transient('wsn_profile_last_error');

6. CONTROLLER (WooPay)
   WsnMerchantProfileController::handle_upsert
     ├── permission_callback = RESTUtils::is_valid_request_signature_within_acceptance_window
     │     (HMAC-SHA512 over blog_id . time_step, 2-step acceptance window,
     │      pattern from UserExistsRestController)
     ├── Assert {blog_id} in path === blog_id recovered from Jetpack signature
     ├── Validate schema (json-schema): reject any payload containing
     │     'street_address' / 'postcode' / etc. (belt-and-suspenders privacy fence)
     ├── Conflict-resolve:
     │     IF stored.payload_version != incoming.payload_version
     │     OR stored.client_updated_at < incoming.client_updated_at THEN upsert
     │     ELSE return 200 no-op
     └── WsnMerchantProfileDataStore::upsert($blog_id, $payload)

7. DB WRITE
   INSERT INTO wp_wsn_merchant_profile (...) VALUES (...) ON DUPLICATE KEY UPDATE ...
   wp_cache_delete("wsn_merchant_profile:{$blog_id}", 'wsn')
   wp_cache_delete("wsn_merchant_profile_by_host:{$host}", 'wsn')
   Returns { stored_version, updated_at } in 200 response
```

End-to-end budget: trigger → DB write ≈ 60s (debounce) + ~500ms (POST round-trip).

## Storefront contract reconciliation

The WSN storefront on the WooPay side **already has consumer code wired** — the hero reads a merchant logo and a 4-column policies footer (About / Shipping / Returns / Contact) reads merchant policy fields. They render null today because the values aren't populated anywhere — the ES projection (`src/Shop/Marketplace/EsSource.php`) intentionally returns null for these fields, and per Option B (LOCKED 2026-05-31) it stays that way. The Profile push lights them up via the storefront `/wsn/v1/stores/{host}` handler, which reads the Profile table AFTER ES and merges the fields onto the store object.

**Layer ownership — producer-stability principle (LOCKED 2026-06-04):** WCPay emits the **canonical** WCPay-side shape (`derivations.tagline`, `derivations.free_shipping.human_summary`, separate `refund_page_url` + `refund_page_label`, etc.). The four field renames into the storefront contract (`store.description`, `store.shipping_promise`, `store.return_policy: {url, label}`) live on the **WooPay-side handler** — applied at merge time inside `/wsn/v1/stores/{host}`, alongside the Profile-table read. WCPay never knows or asserts the storefront field names; WooPay can rename freely without coordinated WCPay releases. The table below documents the mapping the WooPay handler applies — it is no longer "what the composer does," it is "what the WooPay handler does."

> **Why the flip:** an earlier draft of this doc (and the handoff doc that informed it) recommended pre-aligning in the composer "because it's cheapest." That guidance optimized for one consumer at write time but coupled WCPay's release cadence to WooPay's UI naming. For a payments plugin handling money, producer stability outweighs the small WooPay-side cost of a 20-line projection. Canonical-emit was locked 2026-06-04; this section's "Change" column flipped from "in composer" to "in handler" accordingly.

> **Source:** the field-by-field mapping was authored by the WooPay-side Claude Code session and lives at `.claude/tmp/artifacts/wsn-profile-storefront-handoff.md` (gitignored — local-only; reference but don't link from public docs). The on-wire references it points at: `client/shop/utils/marketplace-adapters.js::marketplaceStoreByUrlToStorefront`, `client/shop/components/shop-policies/index.js`, `client/shop/pages/storefront/index.js`, and `src/Shop/Marketplace/EsSource.php` — all in the woopay repo.

**The storefront contract** (`store.*` fields consumed by the adapter → UI):

| `store.*` field | Rendered by | Shape |
|---|---|---|
| `store.logo_url` | hero logo (`shop.logo`) | image URL |
| `store.description` | footer "About" | plain-text tagline |
| `store.contact_email` | footer "Contact" | email string (`mailto:`) |
| `store.return_policy` | footer "Returns" | **plain text today** (needs link upgrade — see below) |
| `store.shipping_promise` | footer "Shipping" | **flat string** |
| `store.extracted_brand` | PDP/storefront brand tint (`--m-bg` / accent) | object (already consumed) |

The `ShopPolicies` footer returns `null` when all four policy fields are empty — which is why most storefronts show no footer at all today.

### The 4 reconciliations — applied by the WooPay-side `/wsn/v1/stores/{host}` handler

| # | Composer wire field | Storefront expects | Handler-side transform | Linear |
|---|---|---|---|---|
| 1 | `derivations.logo_url` + `payload.logo_dimensions: {width, height}` | `store.logo_url` + dimensions metadata | **Passthrough** the URL; surface the dimensions for the wordmark-swap aspect-ratio gate. | WOOPAY-458 |
| 2 | `derivations.tagline` | `store.description` | **Rename** (assign `store.description = derivations.tagline`). | — |
| 3 | `derivations.free_shipping.human_summary` (nested) | `store.shipping_promise` (flat string) | **Flatten**: `store.shipping_promise = derivations.free_shipping?.human_summary ?? null`. The full `free_shipping` object remains in the payload for any non-footer consumer. | — |
| 4 | `derivations.refund_page_url` + `derivations.refund_page_label` (two separate fields) | `store.return_policy` | **Bundle**: `store.return_policy = { url: derivations.refund_page_url, label: derivations.refund_page_label }`. Footer renders `<a href={url}>{label || url}</a>` — rendering already exists, just needs data. | RSM-3945 |

### Decision: composer emits the URL and label as separate fields; handler bundles

**The user must be able to read the URL.** Plain-text rendering of a long refund-policy URL is unreadable and untrustworthy. The footer linkification path already exists on the WooPay side; it just needs data flowing into it.

**Resolution:** the composer emits `derivations.refund_page_url` and `derivations.refund_page_label` as canonical separate fields (matching what the WCPay Profile UI already uses). The WooPay-side handler bundles them into `store.return_policy: { url, label }` at merge time, and the footer renders `<a href={url}>{label || url}</a>` against the bundled shape. The bundling lives on the WooPay side because (a) it is a display concern, not a data concern, and (b) keeping canonical fields separate means a future surface that wants just the URL or just the label doesn't have to re-split.

### Anti-patterns (from the handoff — enforce in this path)

- **No name-search / fuzzy resolution fallback** anywhere in this Profile path. Resolving merchant/store data by approximate name match on a payments surface risks binding the wrong store's data — documented WSN no-go. Use `blog_id` exclusively. See WOOPAY-454.
- **No address PII** — never emit `woocommerce_store_address*` or `woocommerce_store_postcode`. Country / region / city only. Already documented under "Privacy invariants" and enforced 3× (composer allowlist, controller schema, unit tests).

## Table schema

Lives on `pay.woo.com` (WPCOM Simple). Custom InnoDB table; single global table; PK on `blog_id`. Mirrors the WooPay-side `Favorites/DataStore.php` pattern: hand-rolled repository, `$wpdb->prepare`-gated reads/writes, whitelist validation at the repo boundary.

```sql
CREATE TABLE wp_wsn_merchant_profile (
  -- Identity & keying
  blog_id           BIGINT UNSIGNED      NOT NULL,
  host              VARCHAR(255)         NOT NULL,
                    -- Denormalized from Jetpack site mapping; mutable but unique
                    -- at any point in time. Storefront route /shop/<host> looks
                    -- up by host; admin/debug looks up by blog_id.

  -- Versioning & ordering (idempotency + last-write-wins)
  schema_version    SMALLINT UNSIGNED    NOT NULL DEFAULT 1,
                    -- Bump only on incompatible payload-shape changes.
  payload_version   VARCHAR(64)          NOT NULL,
                    -- sha256(serialized payload) from emitter.
                    -- Used for skip-emit (merchant side) AND for If-None-Match
                    -- semantics on the upsert (server side).
  client_updated_at DATETIME             NOT NULL,
                    -- Merchant-site wall clock at compose time.
                    -- Used alongside payload_version in conflict resolution.

  -- Flat indexed identity (queryable, debuggable, hot-path readable)
  shop_name         VARCHAR(255)         NOT NULL DEFAULT '',
  tagline           VARCHAR(500)         NOT NULL DEFAULT '',
  contact_email     VARCHAR(254)         NULL,
                    -- NULL = no override AND no WC fallback resolvable
                    -- ''   = merchant explicitly opted out (suppress contact)
  theme_type        ENUM('block','classic') NOT NULL DEFAULT 'classic',
  refund_url        VARCHAR(2048)        NULL,
  refund_label      VARCHAR(255)         NULL,

  -- Promoted from JSON to flat columns (likely filtered on)
  country           CHAR(2)              NULL,
                    -- ISO 3166-1 alpha-2. For "show me merchants near me" geo filtering.
  has_free_shipping TINYINT(1) UNSIGNED  NOT NULL DEFAULT 0,
                    -- For "merchants offering free shipping" marketplace filter.

  -- Logo & hero (URLs; served via Photon at render time — see "Asset handling")
  logo_url          VARCHAR(2048)        NULL,
  logo_source       ENUM('override','custom_logo','site_logo','site_icon','none')
                                         NOT NULL DEFAULT 'none',
  hero_url          VARCHAR(2048)        NULL,
  logo_thumb_b64    MEDIUMTEXT           NULL,
                    -- Inline ≤8KB base64 thumbnail for above-the-fold LCP image.
                    -- Composer writes only when result <=8KB; storefront uses
                    -- it for the first paint then swaps to Photon-served full URL.

  -- Opaque JSON blobs (storefront reads as-is, never SQL-queried)
  appearance        JSON                 NULL,
                    -- Stripe Elements appearance object. NULL for classic-theme
                    -- merchants who have not had a shopper trigger DOM extraction;
                    -- read path must handle null gracefully.
  font_rules        JSON                 NULL,
  extracted_brand   JSON                 NULL,
                    -- Auto-extracted color tokens for filling appearance gaps.
  shipping          JSON                 NULL,
                    -- Array of { zone_name, regions[], methods[] }.
  free_shipping     JSON                 NULL,
                    -- { human_summary, has_free_shipping, zones[{zone_name, min_amount, requires}] }.
  identity          JSON                 NULL,
                    -- Reserved for additional non-indexed identity fields.
  location          JSON                 NULL,
                    -- ONLY {country, region, city}. Street + postcode MUST
                    -- NEVER appear (enforced 3x: emitter allowlist, controller
                    -- schema validator, unit test on serialized payload).

  -- Bookkeeping
  created_at        TIMESTAMP            NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP            NOT NULL DEFAULT CURRENT_TIMESTAMP
                                         ON UPDATE CURRENT_TIMESTAMP,
  last_seen_at      TIMESTAMP            NULL,
                    -- Updated on every push (including no-op). Lets reconciliation
                    -- cron find merchants we haven't heard from in >7d → GET their
                    -- profile to verify they didn't disconnect → DELETE row if so.

  PRIMARY KEY (blog_id),
  UNIQUE KEY uniq_host (host),
  KEY idx_country (country),
  KEY idx_free_ship (has_free_shipping),
  KEY idx_updated_at (updated_at),
  KEY idx_last_seen (last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Design notes:**

- **PK is `blog_id`, not `host`.** Host is mutable (domain change, A8C-managed redirect); blog_id is permanent.
- **Hybrid flat + JSON.** Flat for what the storefront filters/debugs/sorts on (`country`, `has_free_shipping`, `shop_name`, `contact_email`, `theme_type`); JSON for opaque blobs the storefront feeds straight into Stripe Elements / templates. Avoids JSON path queries on hot fields.
- **No partitioning, no sharding** at v1 scale (sub-100K merchants × ~10KB row ≈ 1GB). Revisit at 1M+.
- **No FK to `WooPayEnabledMerchants`** (which is derived from order meta — orthogonal concern: "merchant accepts WooPay checkout" vs "merchant has a WSN storefront theme"). A row in `wp_wsn_merchant_profile` IS the source-of-truth that this merchant has opted in to WSN theming.
- **Promoted columns (`country`, `has_free_shipping`)** added in v1 because adding columns is cheap (instant DDL in MySQL 8 + backfill on next push) but going the other direction (column → JSON because we promoted prematurely) requires data migration. Better to over-promote slightly than to chase ALTERs after launch.

## Asset handling

**Hybrid: store merchant origin URL in the table, serve through Photon (`i0.wp.com`) at render time. Inline base64 thumbnail for the logo's first-paint LCP.**

```html
<!-- DB row: logo_url = "https://merchantsite.com/wp-content/uploads/2024/logo.png" -->
<img src="https://i0.wp.com/merchantsite.com/wp-content/uploads/2024/logo.png?w=240&ssl=1">
<link rel="preload" as="image" href="data:image/jpeg;base64,..."> <!-- from logo_thumb_b64 -->
```

**Why:**

- **Photon is already available** on WPCOM Simple sites — zero new infra. Does resize-on-the-fly, HTTPS-upgrade, global edge caching, stale-good masking on origin 5xx.
- **Mirror-to-WPCOM-storage** rejected: ACL provisioning, sync-on-update, GC for abandoned assets, double storage cost — no payoff over Photon at this scale.
- **Direct-merchant-URL without CDN** rejected: couples storefront uptime to every merchant's WP host.
- **`logo_thumb_b64`** kills the LCP wait for the most-visible above-the-fold image. Generated by the composer (load attachment, resize ~40×40, base64; skip if result >8KB).

**Failure modes:**

- Merchant deletes the underlying attachment → Photon returns 404 → storefront falls through to WSN neutral chrome via `onerror`. Weekly reconciliation cron re-fetches and marks `logo_url=NULL` so cached-bad URLs self-heal.
- Photon outage → degrades to merchant origin URL. Acceptable transient.
- Merchant changes domain → Jetpack still sends correct blog_id; `host` column updates on next push; old Photon edge cache entries expire naturally.

**Not mirrored:** hero image, product images, gallery. Photon handles them at the merchant origin.

## Read path

Hot path: `/shop/<host>` storefront render.

```
GET /shop/northernpizzaequipment.com → /wsn/v1/stores/{host} handler

1. Resolve host → blog_id
   (existing ES indexer carries this; ~5-15ms cold, ~0.2ms cached)

2. Read ES store object via EsSource::project_store_full()
   (UNCHANGED — projection still returns null for logo_url / contact_email /
    return_policy / shipping_promise / etc. — see comments
    "❌ not indexed — pending Merchant Hub sync". ES handles product-catalog
    indexing via WC's native catalog-visibility signals, separate from the
    Profile read path; the Profile fields below are filled by step 4, not
    by editing this projection.)

3. WsnMerchantProfileDataStore::get_by_blog_id($blog_id)
   ├── L1: wp_cache_get("wsn_merchant_profile:{$blog_id}", 'wsn')
   │       HIT (~99% expected): return cached row. ~0.2ms. DONE.
   └── MISS:
       ├── SELECT * FROM wp_wsn_merchant_profile WHERE blog_id = %d
       │   (PK lookup, ~0.5-2ms on InnoDB)
       ├── wp_cache_set(..., HOUR_IN_SECONDS)
       └── Return row

4. Merge Profile row onto the store object
   (handler-side, NOT inside EsSource. Apply the field renames from the
    Storefront contract reconciliation: profile.tagline → store.description,
    profile.shipping_promise → store.shipping_promise, profile.return_policy
    → store.return_policy, profile.logo_url → store.logo_url, etc.)

5. marketplaceStoreByUrlToStorefront(store) → shop
   (unchanged adapter; receives the merged store object)
```

**Cache invalidation (write path):** the controller's upsert fires `wp_cache_delete` on both keys (`wsn_merchant_profile:{blog_id}` and `wsn_merchant_profile_by_host:{host}`) immediately on successful write.

**Lazy-fetch fallback** (first storefront hit for a not-yet-pushed merchant — happens to merchants who opted in just before the shopper arrived, or after reconciliation marked the row deleted, etc.):

```
If DataStore::get_by_blog_id($blog_id) returns null AND $es_doc indicates WSN-enabled:
  → Schedule Action Scheduler job: wsn_lazy_fetch_profile($blog_id)
       → GET https://public-api.wordpress.com/wpcom/v2/sites/{blog_id}
              /wcpay/wsn/profile-export
       → On 2xx: DataStore::upsert($blog_id, $payload), invalidate cache
  → Current request renders with null fields (storefront falls back to neutral chrome)
  → Subsequent request within ~minute hits the populated row
```

**Latency budget:**

| Step | Cold | Warm |
|---|---|---|
| host → blog_id (ES, cached) | ~10ms | ~0.2ms |
| DataStore (PK + object cache) | ~1ms | ~0.2ms |
| Projection + merge | ~0.1ms | ~0.1ms |
| **Total contribution to render** | **~11ms** | **~0.5ms** |

At 1M merchants × 10 RPS hot: object cache eats >99% of reads; DB sees <100K QPS in worst case, well within single-host InnoDB on PK.

**Out of scope for this table:** marketplace browse/search (ES `wcbazaar` index), geographic distance queries (need lat/lng), Visibility rules (Jetpack-sync'd, separate WPCOM read path).

## Failure modes & recovery

**(a) Push fails (merchant-side: emit POST gets non-2xx or throws).** Try/catch swallows the Throwable (matches `WC_Payments_Account::store_setup_sync` pattern). Sets `wsn_profile_last_error` transient (7-day TTL). Hub Profile tab surfaces "Last sync failed — Retry sync" button. Three independent recovery paths: (1) merchant clicks Retry → forces immediate POST bypass debounce; (2) 6-hour recurring backstop catches the next interval; (3) any subsequent settings change re-triggers debounce.

**(b) WooPay-side DB write fails.** Controller returns 5xx; merchant-side records `last_error`; same 3 recovery paths as (a). If repeated 5xx across many merchants → operational alert on WooPay side; no Action Scheduler queue on WooPay side needed because the 6h backstop drains the backlog naturally.

**(c) Data drift (merchant intent ≠ WooPay row).** Three causes:

1. **Missed hook fires** (plugin deactivation during emit, fatal during compose, race during deploy) — 6h recurring backstop catches it.
2. **Logo/hero attachment deleted on merchant** — weekly reconciliation cron on WooPay side does GET against merchant; if URL 404s, marks `logo_url=NULL`, updates `last_seen_at`; storefront falls back to neutral chrome.
3. **Classic-theme merchant changed theme but no shopper hit checkout yet** — appearance is null; emitter still pushes (composer handles null); storefront uses neutral chrome until first shopper checkout triggers DOM extraction → `wcpay_woopay_appearance_changed` → emit → row updated.

**(d) Merchant uninstalls WCPay plugin.** Two layers:

1. **Graceful uninstall** — `uninstall.php` fires a final `DELETE /wsn/v1/merchants/{blog_id}/profile` before option cleanup. Try/catch wrapped, fire-and-forget (uninstall context can't reliably do network calls).
2. **Reconciliation cron** (the load-bearing path) — detects `last_seen_at < now() - 7d` → GETs merchant's `/wcpay/wsn/profile-export` → 404 (plugin gone) → DELETE row.

Both ship in v1. Graceful is the happy-path optimization; reconciliation is the correctness guarantee.

**(e) Opt-out without uninstall (`wcpay_wsn_enabled` flipped to false but plugin installed).** Composer detects in compose step → emitter sends DELETE → controller removes row.

**(f) Jetpack disconnect / token rotation.** `is_server_connected()` check in emitter short-circuits; logs; no POST attempted. Hub badge eventually shows "Last synced > 7d ago" warning. Recovery: merchant reconnects Jetpack → next trigger fires → push succeeds. No data loss — `WSN_Settings` wp_options are still intact merchant-side.

**(g) Race: concurrent pushes for same blog_id.** Controller conflict-resolves: `IF stored.payload_version != incoming.payload_version OR stored.client_updated_at < incoming.client_updated_at THEN upsert`. Older push arriving after newer one → 200 no-op. No serialization required for the sparse-write workload.

**(h) Privacy invariant violation (street address leaks into payload).** Defense in depth: (1) emitter composer uses explicit allowlist for `location` fields (`country`, `region`, `city` only); (2) controller schema validator rejects payloads containing fields outside that allowlist; (3) unit tests on both sides assert serialized payload never contains stored `woocommerce_store_address` / `woocommerce_store_postcode` strings. If any layer fails → row write blocked at controller; merchant sees 422; nothing leaks to disk on WooPay side.

## Privacy invariants

The merchant's physical store address (`woocommerce_store_address`, `woocommerce_store_address_2`, `woocommerce_store_postcode`) **must never appear in the payload, in the table, or in any log line on the WooPay side.** Country / region / city are public and shippable; street and postcode are not.

Enforced by three layers, all required:

1. **Composer allowlist** (merchant-side, RSM-3945): location object is built by extracting only `country` / `region` / `city` from WC settings — not by stripping unwanted fields from a larger object. Allowlist > blocklist.
2. **Controller schema validation** (WooPay-side): json-schema rejects payloads where `location` contains anything other than the three allowed keys. Returns 422 on violation.
3. **Unit tests both sides**: assert the serialized JSON string does NOT contain the stored address strings (constants from a test fixture). Catches regressions where a refactor accidentally adds a field to the composed payload.

The reconciliation cron also runs the same schema validator on every GET response from a merchant — so even if a future WCPay version regresses and includes an address field, WooPay refuses to store it.

## Resolved decisions

| # | Decision | Rationale |
|---|---|---|
| 1 | **Jetpack signature auth** (direct POST to WooPay host, locked 2026-06-04) | pay.woo.com is WPCOM Simple — native fit, no shared secret, blog_id auto-recovered from signed envelope. Transport calls [`\Automattic\Jetpack\Connection\Client::remote_request()`](../../includes/wsn/class-wsn-profile-transport.php) directly against the WooPay host. Mirrors the production-proven `/init` push at [class-woopay-session.php::ajax_init_woopay](../../includes/woopay/class-woopay-session.php) — same signer, same WooPay-side validator, in production for years. NOT routed through the WCPay backend `wcpay/*` namespace (an earlier draft of this doc described that path; it was wrong — no handler existed on the WCPay backend). |
| 2 | Endpoint path: **`/wsn/v1/merchants/{blog_id}/profile`** | blog_id is immutable; matches Jetpack-signature recovery; supports POST (push) + GET (lazy-fetch) + DELETE (uninstall). |
| 3 | **Custom InnoDB table** `wp_wsn_merchant_profile` | Mirrors WooPay-side Favorites/Cart pattern; debuggable from CLI; queryable independently of WP options autoload behavior. |
| 4 | **WPCOM ops access confirmed** | No infra blocker. |
| 5 | **1-minute debounce** | Mirrors `Compatibility_Service` Action Scheduler pattern with tighter window for fresher storefront updates; sparse write workload makes shorter window negligibly more expensive. |
| 6 | **`logo_thumb_b64` MEDIUMTEXT ships in v1** | Logo is the LCP candidate above the fold on `/shop/<host>`; inline thumbnail eliminates the Photon round-trip on first paint. |
| 7 | **New sibling GET endpoint** `/wc/v3/payments/wsn/profile-export` on merchant side | Same data shape as the POST payload; `permission_callback = is_valid_jetpack_signature`. Used by reconciliation cron and lazy-fetch fallback. RSM-3945 ships this alongside the emitter. |
| 8 | **Both graceful uninstall AND reconciliation cron** | `uninstall.php` context is unreliable for network calls; reconciliation is the load-bearing correctness layer, graceful is the 95% happy-path optimization. |
| 9 | **Promote `country` + `has_free_shipping` to flat indexed columns** | Likely marketplace filter columns; adding columns later is cheap, removing them isn't. Better to slightly over-promote than to chase ALTERs after launch. |
| 10 | **Opt-in IS the backfill** | WSN Hub is feature-flagged off today; `wcpay_wsn_enabled` defaults to false; no merchant can currently be in the "enrolled but unsynced" state at launch. The first enable click IS the first push. WP-CLI batch backfill becomes a v2 disaster-recovery tool only. |

### Correction: direct-to-WooPay transport (locked 2026-06-04)

An earlier draft of this doc described the Profile push as routing through the WCPay backend at `https://public-api.wordpress.com/wpcom/v2/sites/{blog_id}/wcpay/wsn/profile` — i.e., via `WC_Payments_API_Client::request()`. **That path had no receiver.** The WooPay-side session verified this by greping all 663 endpoint files in `woocommerce-payments-server` for any `wsn` route and finding zero. Every push fired by the original code landed nowhere.

The fix re-points the transport to POST **directly** to the WooPay host (`pay.woo.com/wp-json/wsn/v1/merchants/{blog_id}/profile`), using `\Automattic\Jetpack\Connection\Client::remote_request()` and the standard `X_JETPACK` blog-token signature attached by the Jetpack signer. WooPay validates the signature natively via `Jetpack_Server_Version::get_token_from_authorization_header()` plus a `signed_blog_id === path_blog_id` cross-check (the WOOPAY-454 defense-in-depth pattern). The existence proof: the `/init` express-checkout push at [`includes/woopay/class-woopay-session.php::ajax_init_woopay()`](../../includes/woopay/class-woopay-session.php) has been doing this exact `Client::remote_request()` → WooPay host → blog-token-signature-validated pattern in production for years.

The wire shape (composer payload, `payload_version` hash, privacy allowlist, `schema_version`) is unchanged. Only the transport layer was wrong; the contract was right.

Source of the correction: [`.claude/tmp/artifacts/RSM-3945/handoff-to-wcpay-option-c.md`](../tmp/artifacts/RSM-3945/handoff-to-wcpay-option-c.md) — WooPay-side audit + recommended fix (Option C).

## Implementation plan (RSM-3945 scope)

**WCPay merchant-side (new files):**

- `includes/wsn/class-wsn-profile-payload-composer.php` — `WSN_Profile_Payload_Composer::compose()` returning the wire payload (settings + derivations + appearance + location-allowlisted + logo_thumb_b64 + version)
- `includes/wsn/class-wsn-profile-transport.php` — `WSN_Profile_Transport` direct Jetpack-signed POST/DELETE to the WooPay host; called by the emitter on push and by `uninstall.php` on goodbye-DELETE
- `includes/wsn/class-wsn-profile-emitter.php` — `WSN_Profile_Emitter` listens on the 3 trigger hooks, debounces via Action Scheduler, fires the Jetpack-signed POST via the transport, manages `wsn_profile_last_synced` + `wsn_profile_last_error` state
- `includes/admin/class-wc-rest-payments-wsn-profile-export-controller.php` — new sibling GET endpoint at `/wc/v3/payments/wsn/profile-export`, `permission_callback = is_valid_jetpack_signature`, returns the same payload shape the composer emits
- `uninstall.php` addition — fire-and-forget DELETE call via the transport to `pay.woo.com/wp-json/wsn/v1/merchants/{blog_id}/profile`

**WCPay merchant-side (modifications):**

- `includes/class-wc-payments-styles-cache.php` — add `do_action('wcpay_woopay_appearance_changed', $appearance, $font_rules, $version)` at the end of `set_woopay_appearance()`
- `includes/wsn/class-wsn-hub.php` — register the emitter on appropriate boot hooks
- Feature flag: `wcpay_wsn_profile_emitter_enabled` defaults OFF

**WooPay-side (new files):**

- `src/Shop/Marketplace/WsnMerchantProfileController.php` — POST/GET/DELETE routes at `/wsn/v1/merchants/{blog_id}/profile`, Jetpack-signature `permission_callback`, conflict resolution, schema validation
- `src/Shop/Marketplace/WsnMerchantProfileDataStore.php` — hand-rolled repository (mirrors `src/Favorites/DataStore.php`), `upsert`, `get_by_blog_id`, `get_by_host`, `delete_by_blog_id`
- `src/Shop/Marketplace/WsnMerchantProfileMigration.php` — `dbDelta` install/upgrade per the `Favorites/Init.php` pattern, registers with `woocommerce_install_get_tables`
- Reconciliation cron job — weekly, iterates rows with `last_seen_at < now() - 7d`, GETs merchant export endpoint, deletes 404s
- Lazy-fetch Action Scheduler job — `wsn_lazy_fetch_profile($blog_id)`, called on storefront read-path miss
- Feature flag: `wsn_merchant_profile_endpoint_enabled` defaults OFF; `wsn_storefront_read_from_profile_table` defaults OFF

**WooPay-side (modifications):**

- `src/Shop/Marketplace/StoresHandler.php` (or the `/wsn/v1/stores/{host}` handler equivalent) — call `WsnMerchantProfileDataStore::get_by_blog_id` AFTER `EsSource::project_store_full()` returns, then merge the Profile row's fields onto the store object (`logo_url`, `description`, `contact_email`, `return_policy`, `shipping_promise`, etc.). The field-rename mapping from "Storefront contract reconciliation" is applied here.
- `src/Shop/Marketplace/EsSource.php::project_store_full()` — UNCHANGED. Per Option B the ES projection stays simple (still returns `null` for the Profile-owned fields); the handler-side merge is what fills them. Earlier draft of this doc described editing project_store_full — that path was superseded 2026-05-31.

**Rollout sequence:**

| Phase | What | Where | Flag |
|---|---|---|---|
| 0 | Architecture sign-off + this doc | — | — |
| 1 | Table migration + controller + datastore + reconciliation cron skeleton | WooPay | `wsn_merchant_profile_endpoint_enabled` |
| 2 | Composer + emitter + sibling GET endpoint, gated cohort | WCPay | `wcpay_wsn_profile_emitter_enabled` |
| 3 | Storefront read path consumes the table | WooPay | `wsn_storefront_read_from_profile_table` |
| 4 | Production cutover — flip flags for all merchants; reconciliation cron starts | both | — |

No backfill phase. Opt-in is the population.

## Companion: site logo via Jetpack Sync (separate transport, broader use case)

The WSN Profile push above carries the **resolved** `logo_url` (override → custom_logo → site_logo → site_icon → null), which is exactly what the WSN storefront needs. But WooPay has other surfaces — marketplace browse cards, store-listing indexers, future per-merchant chrome — that need the site logo for **non-WSN-opted-in merchants too**, because the Profile push only fires for merchants who toggle WSN on.

The fix is independent of the Profile-push pipe: add the site logo source options to the Jetpack sync whitelist so they mirror to WooPay automatically alongside `blogname` / `blogdescription` etc.

**Shipped** ([woocommerce-payments.php:127-160](../../woocommerce-payments.php#L127-L160)):

```php
'jetpack_sync_options_whitelist' => [
    'active_plugins',
    'blogdescription',
    'blogname',
    'timezone_string',
    'gmt_offset',
    'site_logo',                              // block themes (Site Editor)
    'site_icon',                              // favicon, used as last-resort fallback
],
'jetpack_sync_callable_whitelist' => [
    'wcpay_custom_logo_attachment_id' =>      // classic themes (~85% of sites)
        static fn() => (int) get_theme_mod( 'custom_logo' ),
],
```

Block themes set `option site_logo`. Classic themes set `theme_mod custom_logo` (a serialized array inside `theme_mods_<theme>`, which the options whitelist can't mirror — Jetpack syncs callable return values instead). Both paths need to land for WooPay to support the majority of WooCommerce stores; classic themes are still the dominant share, so neither path is optional.

**Read-path on WooPay:** consumers check both, mirroring the WCPay-side `compute_derivations()` fallback chain:

```php
$logo_id = (int) get_blog_option( $blog_id, 'site_logo' );           // block themes
if ( ! $logo_id ) {
    $logo_id = Jetpack_Sync_Callables::get( $blog_id, 'wcpay_custom_logo_attachment_id' );  // classic
}
if ( ! $logo_id ) {
    $logo_id = (int) get_blog_option( $blog_id, 'site_icon' );       // favicon fallback
}
```

Attachment IDs only — to render, resolve to a URL via the merchant's REST media endpoint, or (preferred) include `logo_url` directly in the WSN Profile push for WSN-themed merchants. The order above matches WCPay's own resolver in `compute_derivations()` so the two surfaces don't drift.

## Cross-repo follow-up

The WooPay-side `docs/wsn/merchant-appearance-sync.md` has an internal contradiction (TL;DR says wp_options-per-blog; Architecture section says custom table keyed on host). This doc supersedes both:

- Storage substrate: custom table `wp_wsn_merchant_profile`
- Keying: blog_id (PK) with host as a unique secondary index
- Auth: Jetpack signature
- Endpoint path: `/wsn/v1/merchants/{blog_id}/profile`

That woopay-repo doc should be edited or marked superseded by whoever owns it. `api-contract.md` §1 currently specifies Bearer auth — superseded by the Jetpack-signature decision (locked 2026-06-04; rationale below in "Resolved decisions"). The doc needs a follow-up edit but the choice is not blocked on it.

## References

- **Linear:**
  - [RSM-3930](https://linear.app/a8c/issue/RSM-3930) — WSN Hub epic
  - [RSM-3945](https://linear.app/a8c/issue/RSM-3945) — Profile emitter (this work)
  - [RSM-2481](https://linear.app/a8c/issue/RSM-2481) — Profile tab UI (already shipped in the umbrella PR)
  - [WOOPAY-458](https://linear.app/a8c/issue/WOOPAY-458) — Storefront logo path: Hub's resolved `logo_url` → `store.logo_url` (closed by composer passthrough)
  - [WOOPAY-454](https://linear.app/a8c/issue/WOOPAY-454) — No name-search / fuzzy resolution on payments surfaces (enforce blog_id-only lookups in this Profile path)
- **Operational guides:**
  - [wsn-integration-testing.md](./wsn-integration-testing.md) — runbook for end-to-end integration testing between WCPay and a WooPay sandbox (setup, triggers, verification, troubleshooting).
- **WCPay code:**
  - [class-wsn-settings.php](../../includes/wsn/class-wsn-settings.php) — option storage
  - [class-wc-rest-payments-wsn-settings-controller.php](../../includes/admin/class-wc-rest-payments-wsn-settings-controller.php) — `compute_derivations()` is the canonical payload shape
  - [class-wc-payments-styles-cache.php](../../includes/class-wc-payments-styles-cache.php) — appearance source
  - [class-wsn-profile-transport.php](../../includes/wsn/class-wsn-profile-transport.php) — direct Jetpack-signed POST/DELETE to the WooPay host
  - [class-woopay-session.php](../../includes/woopay/class-woopay-session.php) — `ajax_init_woopay()` is the production existence-proof for the direct-to-WooPay Jetpack-signed transport pattern
  - [class-compatibility-service.php](../../includes/compatibility/class-compatibility-service.php) — debounce pattern reference
- **WooPay code** (in the woopay repo):
  - `src/Shop/Marketplace/MarketplaceController.php` — controller pattern
  - `src/Favorites/DataStore.php` — datastore pattern
  - `src/Favorites/Init.php` — table install pattern
  - `src/RESTUtils.php::is_valid_request_signature_within_acceptance_window` — auth pattern
  - `src/User/UserExistsRestController.php` — existing Jetpack-signed inbound endpoint reference
  - `client/shop/utils/marketplace-adapters.js::marketplaceStoreByUrlToStorefront` — store.* → shop.* adapter (consumer of the merged store object)
  - `client/shop/components/shop-policies/index.js` — footer policies render (consumes description, contact_email, return_policy, shipping_promise)
  - `client/shop/pages/storefront/index.js` — hero render (consumes shop.logo from store.logo_url)
  - `src/Shop/Marketplace/EsSource.php::project_store_full` — ES projection (UNCHANGED under Option B; Profile data is merged at the storefront handler, NOT here)
- **External:**
  - `docs/wsn/merchant-appearance-sync.md` (woopay repo) — partially superseded by this doc
  - `docs/wsn/api-contract.md` (woopay repo) — §1 (auth) specifies Bearer, superseded by Jetpack signature decision (locked 2026-06-04). Needs a follow-up edit but the choice is not blocked on it.
  - `docs/wsn/endpoints.md` (woopay repo) — current storefront read shape
