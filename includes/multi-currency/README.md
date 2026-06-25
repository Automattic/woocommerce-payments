# WooPayments multi-currency directory

This directory contains the multi-currency module, which has been decoupled and extracted from the gateway code.

The module is responsible for handling all multi-currency functionality, both back-end and front-end.

## Cache-optimized rendering mode

Multi-currency supports two price rendering modes (Payments → Multi-currency → Store settings):

- **Optimized for speed** (default): prices are converted server-side. Each visitor's currency is baked into the HTML, which prevents effective full-page caching.
- **Optimized for caching**: the server emits identical placeholder markup for all anonymous visitors and a small client-side renderer converts prices via a public REST endpoint, so pages stay cacheable.

WooPayments detects whether the site uses full-page caching and either auto-enables the caching mode (only on fresh installs that have never configured a mode) or surfaces a dismissible recommendation on the settings page. See `CachingEnvironment` and `MultiCurrency::maybe_auto_enable_cache_rendering_mode()`.

### For hosting providers

Built-in detection recognizes common page-cache **plugins** and the page-cache **drop-in** (`WP_CACHE` + `advanced-cache.php`), plus a few managed hosts. It cannot reliably detect **server- or edge-level caches** (Varnish, Cloudflare, server-mode LiteSpeed, a platform edge cache), because those expose no PHP signal.

If you operate the caching layer, you can declare it authoritatively with the `wcpay_multi_currency_page_caching_active` filter:

```php
add_filter( 'wcpay_multi_currency_page_caching_active', '__return_true' );
```

**When it's evaluated.** During admin requests: the one-time auto-enable runs on `admin_init`, and the settings page reads the recommendation from the REST `get-settings` response. Register the filter early — a must-use plugin is ideal, since `mu-plugins` load before regular plugins and before these run.

**Typical usage.** A managed host ships a must-use plugin on every site and gates the signal on its own per-site cache state, rather than forcing it blanket-on:

```php
add_filter(
	'wcpay_multi_currency_page_caching_active',
	function ( $detected ) {
		if ( defined( 'ACMEHOST_EDGE_CACHE' ) && ACMEHOST_EDGE_CACHE ) {
			return true;  // We know our edge cache serves this site.
		}
		return $detected; // Otherwise leave WooPayments' own detection untouched.
	}
);
```

**Notes.**

- Returning `true` forces "caching is active"; returning `false` opts out even when WooPayments would otherwise detect a cache. Returning the passed-in value (or not hooking at all) leaves the built-in detection in charge.
- The signal only drives the *recommendation* and the *auto-enable* decision — it never changes how prices render, and merchants can always override the rendering mode in the settings.
- Existing installations are never auto-switched on upgrade; they only ever see the recommendation. Auto-enable applies to fresh installs.
