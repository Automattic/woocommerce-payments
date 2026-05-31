<?php
/**
 * Class WSN_Settings
 *
 * Get/set helpers for all merchant-facing Shopping Network Hub settings.
 *
 * Storage: per-key wp_options under the `wcpay_wsn_*` prefix. All options are
 * autoload=false (the Hub is feature-flagged off by default; we don't want to
 * pay autoload cost on every WP request for merchants who haven't opted in).
 *
 * Default-value rule: every getter passes a default to get_option(). NEVER
 * auto-write a default on activation. Unset is a meaningful state (merchant
 * has never visited the Hub) — see is_enabled() docblock for the why.
 *
 * @package WooCommerce\Payments\WSN
 */

defined( 'ABSPATH' ) || exit;

/**
 * WSN_Settings — wp_options read/write helpers for the Shopping Network Hub.
 */
class WSN_Settings {

	const OPTION_ENABLED                = 'wcpay_wsn_enabled';
	const OPTION_VISIBILITY_MODE        = 'wcpay_wsn_visibility_mode';
	const OPTION_VISIBILITY_TERMS       = 'wcpay_wsn_visibility_terms';
	const OPTION_VISIBILITY_PRODUCT_IDS = 'wcpay_wsn_visibility_product_ids';
	const OPTION_HERO_IMAGE_ID          = 'wcpay_wsn_hero_image_id';
	const OPTION_LOGO_OVERRIDE_ID       = 'wcpay_wsn_logo_override_id';
	const OPTION_CONTACT_EMAIL          = 'wcpay_wsn_contact_email';
	const OPTION_REFUND_PAGE_ID         = 'wcpay_wsn_refund_page_id';

	const VISIBILITY_MODE_ALL      = 'all';
	const VISIBILITY_MODE_TAXONOMY = 'taxonomy';
	const VISIBILITY_MODE_SPECIFIC = 'specific';

	const MAX_SPECIFIC_PRODUCT_IDS = 1000;

	/**
	 * Whether the merchant has opted in to the Shopping Network.
	 *
	 * Returns false when the option is unset OR explicitly '0'. The unset case is
	 * preserved as distinct from '0' at the storage layer (see set_enabled()) so
	 * downstream re-engagement code can tell "never engaged" from "explicitly disabled."
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		return '1' === get_option( self::OPTION_ENABLED, '0' );
	}

	/**
	 * Sets the merchant's Shopping Network opt-in state.
	 *
	 * Writes '1' or '0' explicitly. We do NOT delete the option on opt-out —
	 * absence-of-key carries the "never engaged" signal and is reserved for that.
	 *
	 * @param bool $enabled Whether the merchant has opted in.
	 */
	public static function set_enabled( bool $enabled ): void {
		update_option( self::OPTION_ENABLED, $enabled ? '1' : '0', false );
	}

	/**
	 * Returns the merchant's product-visibility mode.
	 *
	 * @return string One of VISIBILITY_MODE_ALL, VISIBILITY_MODE_TAXONOMY, VISIBILITY_MODE_SPECIFIC.
	 */
	public static function get_visibility_mode(): string {
		$mode = get_option( self::OPTION_VISIBILITY_MODE, self::VISIBILITY_MODE_ALL );
		if ( ! in_array( $mode, self::valid_visibility_modes(), true ) ) {
			return self::VISIBILITY_MODE_ALL;
		}
		return $mode;
	}

	/**
	 * Sets the product-visibility mode.
	 *
	 * @param string $mode One of VISIBILITY_MODE_ALL, VISIBILITY_MODE_TAXONOMY, VISIBILITY_MODE_SPECIFIC.
	 * @return bool True if the value was accepted and persisted, false on validation failure.
	 */
	public static function set_visibility_mode( string $mode ): bool {
		if ( ! in_array( $mode, self::valid_visibility_modes(), true ) ) {
			return false;
		}
		update_option( self::OPTION_VISIBILITY_MODE, $mode, false );
		return true;
	}

	/**
	 * Returns the selected taxonomy term IDs for the 'taxonomy' visibility mode.
	 *
	 * @return array{categories: int[], tags: int[], brands: int[]} Term IDs grouped by taxonomy bucket.
	 */
	public static function get_visibility_terms(): array {
		$stored = get_option( self::OPTION_VISIBILITY_TERMS, [] );
		return [
			'categories' => self::sanitize_id_array( $stored['categories'] ?? [] ),
			'tags'       => self::sanitize_id_array( $stored['tags'] ?? [] ),
			'brands'     => self::sanitize_id_array( $stored['brands'] ?? [] ),
		];
	}

	/**
	 * Sets the selected taxonomy term IDs.
	 *
	 * Accepts a partial structure — missing keys are stored as empty arrays.
	 *
	 * @param array $terms Map of taxonomy bucket → array of term IDs.
	 */
	public static function set_visibility_terms( array $terms ): void {
		$normalized = [
			'categories' => self::sanitize_id_array( $terms['categories'] ?? [] ),
			'tags'       => self::sanitize_id_array( $terms['tags'] ?? [] ),
			'brands'     => self::sanitize_id_array( $terms['brands'] ?? [] ),
		];
		update_option( self::OPTION_VISIBILITY_TERMS, $normalized, false );
	}

	/**
	 * Returns the explicit product ID whitelist for the 'specific' visibility mode.
	 *
	 * @return int[]
	 */
	public static function get_visibility_product_ids(): array {
		return self::sanitize_id_array( get_option( self::OPTION_VISIBILITY_PRODUCT_IDS, [] ) );
	}

	/**
	 * Sets the explicit product ID whitelist.
	 *
	 * Caps at MAX_SPECIFIC_PRODUCT_IDS (1000) per the api-contract.md §3 MVP limit.
	 *
	 * @param int[] $product_ids Product IDs.
	 * @return bool True if accepted, false if the count exceeded the MVP cap.
	 */
	public static function set_visibility_product_ids( array $product_ids ): bool {
		$sanitized = self::sanitize_id_array( $product_ids );
		if ( count( $sanitized ) > self::MAX_SPECIFIC_PRODUCT_IDS ) {
			return false;
		}
		update_option( self::OPTION_VISIBILITY_PRODUCT_IDS, $sanitized, false );
		return true;
	}

	/**
	 * Returns the hero banner image attachment ID, or null if unset.
	 *
	 * @return int|null
	 */
	public static function get_hero_image_id(): ?int {
		$id = get_option( self::OPTION_HERO_IMAGE_ID, null );
		return is_numeric( $id ) && (int) $id > 0 ? (int) $id : null;
	}

	/**
	 * Sets (or clears) the hero banner attachment ID.
	 *
	 * Validates that the input ID resolves to an image attachment (post_type=attachment
	 * with an image/* mime type). Non-image IDs are rejected without persisting — this
	 * prevents a manage_woocommerce user from storing arbitrary post IDs (private
	 * pages, drafts) which would then leak through the `wcpay_wsn_profile_changed`
	 * action payload and the GET /settings response. Mirrors the validation pattern
	 * in set_refund_page_id().
	 *
	 * @param int|null $attachment_id Attachment ID, or null to clear.
	 * @return bool True if accepted (or cleared), false if the ID didn't resolve to an image attachment.
	 */
	public static function set_hero_image_id( ?int $attachment_id ): bool {
		if ( null === $attachment_id || $attachment_id <= 0 ) {
			delete_option( self::OPTION_HERO_IMAGE_ID );
			return true;
		}
		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			return false;
		}
		update_option( self::OPTION_HERO_IMAGE_ID, $attachment_id, false );
		return true;
	}

	/**
	 * Returns the logo-override attachment ID, or null when the site logo should be used instead.
	 *
	 * Per the v2 mockup: the Profile tab defaults to displaying the active site logo
	 * (get_theme_mod('custom_logo')). A non-null override here means the merchant has
	 * chosen a different image for the WSN storefront specifically.
	 *
	 * @return int|null
	 */
	public static function get_logo_override_id(): ?int {
		$id = get_option( self::OPTION_LOGO_OVERRIDE_ID, null );
		return is_numeric( $id ) && (int) $id > 0 ? (int) $id : null;
	}

	/**
	 * Sets (or clears) the logo override.
	 *
	 * Null deletes the option, restoring the default site-logo behavior. Non-null
	 * IDs must resolve to image attachments — see set_hero_image_id() for the
	 * security rationale.
	 *
	 * @param int|null $attachment_id Attachment ID, or null to revert to the site logo.
	 * @return bool True if accepted (or cleared), false if the ID didn't resolve to an image attachment.
	 */
	public static function set_logo_override_id( ?int $attachment_id ): bool {
		if ( null === $attachment_id || $attachment_id <= 0 ) {
			delete_option( self::OPTION_LOGO_OVERRIDE_ID );
			return true;
		}
		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			return false;
		}
		update_option( self::OPTION_LOGO_OVERRIDE_ID, $attachment_id, false );
		return true;
	}

	/**
	 * Returns the merchant's contact-email preference: null = no override
	 * (use WC-derived default), '' = explicit "no contact email", or an
	 * email string for an explicit override.
	 *
	 * Three-state: a missing option means the merchant hasn't expressed a
	 * preference and the composer should fall back to the WC-derived default
	 * (`woocommerce_email_reply_to_address` when reply-to is configured,
	 * else `woocommerce_email_from_address`). An empty string is a deliberate
	 * "I do not want a contact email shown" choice and MUST be preserved
	 * — the composer treats that as "no contact" rather than re-pulling
	 * the default.
	 *
	 * @return string|null
	 */
	public static function get_contact_email(): ?string {
		$email = get_option( self::OPTION_CONTACT_EMAIL, null );
		// `get_option` returns the literal default (`null`) when the row is
		// absent from the options table, and the stored value (which can be
		// `''` after an explicit-empty save) otherwise. `false` only appears
		// when something else passed `false` as the default — defend against
		// that by treating it as "unset" too.
		if ( null === $email || false === $email ) {
			return null;
		}
		return (string) $email;
	}

	/**
	 * Records the merchant's contact-email preference.
	 *
	 *   - `null` clears the override entirely (composer falls back to default).
	 *   - `''` records "explicit empty" (composer shows no contact email).
	 *   - A non-empty string is sanitized as an email; invalid input returns
	 *     false and the previous stored value is preserved.
	 *
	 * @param string|null $email Email address, '' for explicit-empty, or null to clear.
	 * @return bool True if accepted (including null/empty), false if a non-empty input failed sanitize_email.
	 */
	public static function set_contact_email( ?string $email ): bool {
		if ( null === $email ) {
			delete_option( self::OPTION_CONTACT_EMAIL );
			return true;
		}
		if ( '' === $email ) {
			update_option( self::OPTION_CONTACT_EMAIL, '', false );
			return true;
		}
		$sanitized = sanitize_email( $email );
		if ( '' === $sanitized ) {
			return false;
		}
		update_option( self::OPTION_CONTACT_EMAIL, $sanitized, false );
		return true;
	}

	/**
	 * Resolves the WC-derived default contact email — what the WSN
	 * storefront uses when the merchant has not set their own override
	 * (`get_contact_email() === null`).
	 *
	 * Precedence (matches what shoppers see when they reply to WC emails):
	 *   1. `woocommerce_email_reply_to_address` when `woocommerce_email_reply_to_name`
	 *      is non-empty (signals merchant has configured Reply-To).
	 *   2. `woocommerce_email_from_address` (always-present WC From).
	 *
	 * Returns null when neither resolves to a valid email — caller treats
	 * that as "no default available, leave contact empty".
	 *
	 * @return string|null
	 */
	public static function resolve_default_contact_email(): ?string {
		$reply_to_name = trim( (string) get_option( 'woocommerce_email_reply_to_name', '' ) );
		if ( '' !== $reply_to_name ) {
			$reply_to_addr = trim( (string) get_option( 'woocommerce_email_reply_to_address', '' ) );
			if ( '' !== $reply_to_addr ) {
				$sanitized = sanitize_email( $reply_to_addr );
				if ( '' !== $sanitized ) {
					return $sanitized;
				}
			}
		}

		$from_addr = trim( (string) get_option( 'woocommerce_email_from_address', '' ) );
		if ( '' !== $from_addr ) {
			$sanitized = sanitize_email( $from_addr );
			if ( '' !== $sanitized ) {
				return $sanitized;
			}
		}

		return null;
	}

	/**
	 * Returns the refund-policy page ID, or null if unset / no longer published.
	 *
	 * Returns null when the stored ID points to a page that no longer exists or has been
	 * unpublished — the Profile tab handles this by clearing the picker and surfacing
	 * a "select a page" prompt.
	 *
	 * @return int|null
	 */
	public static function get_refund_page_id(): ?int {
		$id = get_option( self::OPTION_REFUND_PAGE_ID, null );
		if ( ! is_numeric( $id ) || (int) $id <= 0 ) {
			return null;
		}
		$page = get_post( (int) $id );
		if ( ! $page || 'page' !== $page->post_type || 'publish' !== $page->post_status ) {
			return null;
		}
		return (int) $id;
	}

	/**
	 * Sets (or clears) the refund-policy page ID.
	 *
	 * Validates that the ID resolves to a published page before persisting.
	 * Null or 0 clears the option.
	 *
	 * @param int|null $page_id Page ID, or null to clear.
	 * @return bool True if accepted, false if the ID didn't resolve to a published page.
	 */
	public static function set_refund_page_id( ?int $page_id ): bool {
		if ( null === $page_id || $page_id <= 0 ) {
			delete_option( self::OPTION_REFUND_PAGE_ID );
			return true;
		}
		$page = get_post( $page_id );
		if ( ! $page || 'page' !== $page->post_type || 'publish' !== $page->post_status ) {
			return false;
		}
		update_option( self::OPTION_REFUND_PAGE_ID, $page_id, false );
		return true;
	}

	/**
	 * Returns the full settings blob — every option's current value, including resolved defaults.
	 *
	 * Used by the GET /settings REST endpoint.
	 *
	 * @return array
	 */
	public static function get_all(): array {
		return [
			'enabled'                => self::is_enabled(),
			'visibility_mode'        => self::get_visibility_mode(),
			'visibility_terms'       => self::get_visibility_terms(),
			'visibility_product_ids' => self::get_visibility_product_ids(),
			'hero_image_id'          => self::get_hero_image_id(),
			'logo_override_id'       => self::get_logo_override_id(),
			'contact_email'          => self::get_contact_email(),
			'refund_page_id'         => self::get_refund_page_id(),
		];
	}

	/**
	 * The set of accepted visibility-mode string values.
	 *
	 * @return string[]
	 */
	public static function valid_visibility_modes(): array {
		return [
			self::VISIBILITY_MODE_ALL,
			self::VISIBILITY_MODE_TAXONOMY,
			self::VISIBILITY_MODE_SPECIFIC,
		];
	}

	/**
	 * Coerces a mixed input into an array of positive integers, deduplicated and reindexed.
	 *
	 * Drops negative values, zero, and non-numeric entries. Preserves insertion order for
	 * the surviving IDs (important for the specific-products mode where the merchant's
	 * pick order can matter for display).
	 *
	 * @param mixed $input Array (or anything iterable) of mixed values.
	 * @return int[]
	 */
	private static function sanitize_id_array( $input ): array {
		if ( ! is_array( $input ) ) {
			return [];
		}
		$result = [];
		foreach ( $input as $value ) {
			if ( ! is_numeric( $value ) ) {
				continue;
			}
			$id = (int) $value;
			if ( $id <= 0 ) {
				continue;
			}
			$result[] = $id;
		}
		return array_values( array_unique( $result ) );
	}
}
