<?php
/**
 * Class WSN_Profile_Payload_Composer
 *
 * @package WooCommerce\Payments\WSN
 */

defined( 'ABSPATH' ) || exit;

/**
 * Assembles the canonical WSN Profile wire payload — the JSON blob the
 * outbound emitter POSTs to the WooPay server and the sibling GET endpoint
 * returns when WooPay's reconciliation cron / lazy-fetch pulls.
 *
 * **Producer-stability principle (locked 2026-06-04):** the composer emits
 * the canonical WCPay-side shape. It does NOT pre-align field names to
 * what the WooPay storefront's footer or hero expects. Renaming and
 * reshaping for display surfaces is the WooPay-side handler's job
 * (`/wsn/v1/stores/{host}` applies the projection at merge time). This
 * keeps WCPay decoupled from WooPay UI iteration — a payments plugin
 * handling money should not be at risk from footer copy rewrites.
 *
 * The wire contract documented here is the stable contract between
 * WCPay (producer) and WooPay (consumer). WooPay can rename freely
 * downstream of this; WCPay never knows or asserts the display shape.
 *
 * **Privacy invariant (defense in depth):** the merchant's physical
 * store address (`woocommerce_store_address*`, `woocommerce_store_postcode`)
 * MUST NOT appear in this payload. Enforced by an explicit allowlist on
 * the `location` field (country/region/city only — extract, do not strip)
 * and asserted by a regression test that searches the serialized payload
 * for known address-fixture strings. The controller schema validator on
 * the WooPay side is the second defense layer; the composer's allowlist
 * is the first.
 *
 * Owned by RSM-3945.
 */
class WSN_Profile_Payload_Composer {

	/**
	 * Schema version of the emitted payload shape.
	 *
	 * Bump when the shape changes incompatibly (field removed, field
	 * type changed, semantics changed). Adding optional fields does NOT
	 * require a bump; consumers should treat unknown fields as ignorable.
	 * The WooPay-side controller asserts schema_version >= its expected
	 * minimum; an older WooPay receiver with a newer WCPay payload still
	 * accepts the write because the shape is additive-compatible.
	 *
	 * @var int
	 */
	const SCHEMA_VERSION = 1;

	/**
	 * Maximum size of the inline logo thumbnail (base64-encoded bytes).
	 *
	 * The thumbnail is a first-paint optimization for the storefront
	 * hero — inline so the LCP image is in the document body instead
	 * of a separate Photon round-trip. Anything larger than this defeats
	 * the purpose (the table row bloats; the document grows; the
	 * benefit is lost). When the thumbnail would exceed this, drop it
	 * and rely on Photon-served full-size logo only.
	 *
	 * @var int
	 */
	const MAX_LOGO_THUMB_BYTES = 8192;

	/**
	 * Target edge length (pixels) for the inline logo thumbnail.
	 *
	 * Small enough to fit comfortably in MAX_LOGO_THUMB_BYTES after
	 * JPEG encoding for typical merchant logos; large enough that a
	 * 1× viewport render at the hero placement looks crisp. The
	 * full-resolution logo loads asynchronously and replaces this.
	 *
	 * @var int
	 */
	const LOGO_THUMB_EDGE_PX = 40;

	/**
	 * Maximum source-image file size we'll attempt to thumbnail.
	 *
	 * GD and Imagick both decode the entire source image into memory
	 * before resizing. A 10MB PNG can expand to 40-80MB of uncompressed
	 * pixel data inside an Action Scheduler worker that shares PHP
	 * memory with the rest of the request. Above this ceiling, skip
	 * the thumbnail entirely — storefront falls back to the
	 * Photon-served full-size URL, which is correct but slower at
	 * first paint. Better to lose the optimization than to OOM the
	 * AS worker.
	 *
	 * @var int
	 */
	const MAX_LOGO_SOURCE_BYTES = 5 * MB_IN_BYTES;

	/**
	 * Compose the wire payload.
	 *
	 * @return array Canonical Profile payload — see class docblock for
	 *               the stable contract this returns.
	 */
	public static function compose(): array {
		$derivations = WSN_Derivations::compute();

		// Resolve logo dimensions from the same attachment ID that produced
		// derivations.logo_url. The storefront wants to gate a
		// logo-as-wordmark swap on aspect ratio + min resolution, which
		// requires width/height. wp_get_attachment_metadata reads the
		// already-cached _wp_attachment_metadata postmeta — no DB hit
		// beyond what other consumers in the request have likely warmed.
		$logo_dimensions = self::resolve_image_dimensions(
			$derivations['logo_attachment_id'] ?? null
		);

		// The appearance + font_rules + extracted_brand bundle. Pulled
		// directly from the stored option rather than via the public
		// helper because the helper returns appearance-only; we need the
		// full bundle the option holds (font_rules siblings appearance
		// in the same row). NULL for classic-theme merchants who haven't
		// had a shopper trigger DOM extraction yet — the receiver and
		// the storefront read path both handle null gracefully.
		$appearance_blob = self::collect_appearance_blob();

		// Build the canonical payload WITHOUT volatile fields. payload_version
		// is sha256 of these bytes — it MUST be a pure function of content,
		// because the emitter's skip-emit guard compares this hash against
		// the last-synced version to decide whether anything actually
		// changed. Volatile fields (client_updated_at, payload_version
		// itself) get stamped AFTER the hash is computed.
		//
		// CRITICAL: putting client_updated_at (a per-second wall-clock
		// timestamp) into the hash input defeats the guard entirely — every
		// compose() would produce a unique hash and the 6h backstop would
		// fire real POSTs at every tick instead of no-opping when nothing
		// changed. The test asserts identical hashes across consecutive
		// composes; without that test this regression can return silently.
		$payload = [
			'schema_version'  => self::SCHEMA_VERSION,
			'blog_id'         => self::resolve_blog_id(),
			'host'            => self::resolve_host(),
			'settings'        => WSN_Settings::get_all(),
			'derivations'     => $derivations,
			'appearance'      => $appearance_blob['appearance'],
			'font_rules'      => $appearance_blob['font_rules'],
			'logo_dimensions' => $logo_dimensions,
			'logo_thumb_b64'  => self::generate_logo_thumb(
				$derivations['logo_attachment_id'] ?? null
			),
			'location'        => self::collect_location_allowlist(),
		];

		// Hash the content-only payload, then stamp volatile fields.
		$serialized                   = wp_json_encode( $payload );
		$payload['payload_version']   = false === $serialized
			? '' // wp_json_encode failure is exceptional — empty version forces a re-emit attempt next cycle.
			: hash( 'sha256', $serialized );
		$payload['client_updated_at'] = gmdate( 'Y-m-d H:i:s' );

		return $payload;
	}

	/**
	 * Resolve the merchant's blog_id from Jetpack — the WPCOM site ID.
	 *
	 * Mirrored in the payload so the receiver can sanity-check it
	 * against the blog_id recovered from the Jetpack signature. The
	 * signature is authoritative; the payload field is a defense-in-depth
	 * cross-check (and useful for debugging when reading raw rows).
	 *
	 * @return int|null Blog ID, or null when Jetpack is not connected.
	 */
	private static function resolve_blog_id(): ?int {
		if ( ! class_exists( '\Jetpack_Options' ) ) {
			return null;
		}
		$id = \Jetpack_Options::get_option( 'id' );
		return null === $id ? null : (int) $id;
	}

	/**
	 * Resolve the merchant's host string — the unsecured hostname of
	 * the storefront URL. Used by the WooPay receiver as a secondary
	 * unique key (and by the `/wsn/v1/stores/{host}` read handler as
	 * the primary lookup).
	 *
	 * @return string Hostname (no scheme, no path, no port), or empty
	 *                string when home_url is unparseable.
	 */
	private static function resolve_host(): string {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		return is_string( $host ) ? $host : '';
	}

	/**
	 * Collect the location allowlist — country / region / city only.
	 *
	 * Explicit allowlist (not blocklist) so the privacy invariant
	 * survives future code changes: if someone adds another address
	 * field to WC and updates the WSN composer reflexively, this
	 * function's signature makes the omission obvious.
	 *
	 * `woocommerce_default_country` stores country and state in a
	 * single `<COUNTRY>:<STATE>` string (e.g. "US:CA"). Parse it.
	 *
	 * @return array { country: ?string, region: ?string, city: ?string }
	 */
	private static function collect_location_allowlist(): array {
		$default_country = (string) get_option( 'woocommerce_default_country', '' );
		$country         = null;
		$region          = null;
		if ( '' !== $default_country ) {
			$parts   = explode( ':', $default_country, 2 );
			$country = '' !== $parts[0] ? $parts[0] : null;
			$region  = isset( $parts[1] ) && '' !== $parts[1] ? $parts[1] : null;
		}

		$city = (string) get_option( 'woocommerce_store_city', '' );

		return [
			'country' => $country,
			'region'  => $region,
			'city'    => '' !== $city ? $city : null,
		];
	}

	/**
	 * Resolve {width, height} for an attachment ID via its cached
	 * `_wp_attachment_metadata` postmeta. Returns null for missing
	 * IDs, non-image attachments, or metadata without dimensions.
	 *
	 * @param int|null $attachment_id Attachment ID to look up, or null.
	 * @return array|null { width: int, height: int } or null.
	 */
	private static function resolve_image_dimensions( ?int $attachment_id ): ?array {
		if ( null === $attachment_id || $attachment_id <= 0 ) {
			return null;
		}
		$meta = wp_get_attachment_metadata( $attachment_id );
		if ( ! is_array( $meta ) || ! isset( $meta['width'], $meta['height'] ) ) {
			return null;
		}
		return [
			'width'  => (int) $meta['width'],
			'height' => (int) $meta['height'],
		];
	}

	/**
	 * Collect the appearance bundle the styles cache stores.
	 *
	 * Returns { appearance, font_rules } both populated or both null —
	 * they're stored together and consumed together. The styles cache
	 * helper only exposes appearance; we read the raw option to get
	 * font_rules too without a second DB hit.
	 *
	 * Null is normal for classic-theme merchants pre-checkout: DOM
	 * extraction is what populates this for classic themes, and that
	 * only fires when a shopper hits checkout.
	 *
	 * @return array { appearance: array|null, font_rules: array|null }
	 */
	private static function collect_appearance_blob(): array {
		$stored = get_option( 'wcpay_woopay_checkout_appearance' );
		if ( ! is_array( $stored ) || empty( $stored ) ) {
			return [
				'appearance' => null,
				'font_rules' => null,
			];
		}
		return [
			'appearance' => isset( $stored['appearance'] ) && is_array( $stored['appearance'] )
				? $stored['appearance']
				: null,
			'font_rules' => isset( $stored['font_rules'] ) && is_array( $stored['font_rules'] )
				? $stored['font_rules']
				: null,
		];
	}

	/**
	 * Generate a small base64 thumbnail for the logo — inline LCP
	 * optimization for the storefront hero render.
	 *
	 * Drops the thumbnail (returns null) when:
	 *  - No logo is set,
	 *  - WP can't load an image editor,
	 *  - The editor fails to resize / encode,
	 *  - The result exceeds MAX_LOGO_THUMB_BYTES.
	 *
	 * In any of those cases the storefront falls back to the
	 * Photon-served full-size URL — slower first paint but correct.
	 *
	 * @param int|null $attachment_id Attachment ID for the resolved logo.
	 * @return string|null Base64-encoded data URI body, or null.
	 */
	private static function generate_logo_thumb( ?int $attachment_id ): ?string {
		if ( null === $attachment_id || $attachment_id <= 0 ) {
			return null;
		}

		$path = get_attached_file( $attachment_id );
		if ( ! is_string( $path ) || ! file_exists( $path ) ) {
			return null;
		}

		// Memory guard — wp_get_image_editor() decodes the full source
		// into memory before resizing. Refuse oversized sources rather
		// than risk OOMing the AS worker on print-quality logos.
		$file_size = @filesize( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( false === $file_size || $file_size > self::MAX_LOGO_SOURCE_BYTES ) {
			return null;
		}

		$editor = wp_get_image_editor( $path );
		if ( is_wp_error( $editor ) ) {
			return null;
		}

		// Resize keeping aspect ratio, bounded by the target edge.
		$resized = $editor->resize( self::LOGO_THUMB_EDGE_PX, self::LOGO_THUMB_EDGE_PX, false );
		if ( is_wp_error( $resized ) ) {
			return null;
		}

		// Stream into a temp file so we can base64 the bytes without
		// holding a full image object in memory beyond the encoder.
		$tmp = wp_tempnam( 'wsn-logo-thumb' );
		if ( false === $tmp ) {
			return null;
		}

		$saved = $editor->save( $tmp, 'image/jpeg' );
		if ( is_wp_error( $saved ) || ! isset( $saved['path'] ) ) {
			wp_delete_file( $tmp );
			return null;
		}

		$bytes = file_get_contents( $saved['path'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		wp_delete_file( $saved['path'] );

		if ( false === $bytes ) {
			return null;
		}

		$b64 = base64_encode( $bytes ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		if ( strlen( $b64 ) > self::MAX_LOGO_THUMB_BYTES ) {
			return null;
		}
		return $b64;
	}
}
