<?php
/**
 * Slug-derived naming for WooPayments admin notices.
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Derives every identifier a notice needs (user-meta keys, transient keys,
 * script handle, query args, nonce actions, Tracks events) from a single
 * snake_case slug. A legacy or externally-referenced identifier is pinned via a
 * constructor override keyed by the getter name, so the exception lives as data
 * rather than as a method override on a subclass.
 */
class WC_Payments_Notice_Naming {

	/**
	 * Snake_case identifier the rest of the names derive from.
	 *
	 * @var string
	 */
	private $slug;

	/**
	 * Per-getter overrides, keyed by getter name (e.g. 'dismissed_meta_key').
	 *
	 * @var array<string, string>
	 */
	private $overrides;

	/**
	 * Constructor.
	 *
	 * @param string                $slug      Snake_case notice identifier.
	 * @param array<string, string> $overrides Optional getter-name => value overrides.
	 */
	public function __construct( string $slug, array $overrides = [] ) {
		$this->slug      = $slug;
		$this->overrides = $overrides;
	}

	/**
	 * User meta key marking that the current admin dismissed this notice.
	 *
	 * @return string
	 */
	public function dismissed_meta_key(): string {
		return $this->resolve( __FUNCTION__, "wcpay_{$this->slug}_notice_dismissed" );
	}

	/**
	 * User meta key recording when the current admin snoozed this notice.
	 *
	 * @return string
	 */
	public function snoozed_meta_key(): string {
		return $this->resolve( __FUNCTION__, "wcpay_{$this->slug}_notice_snoozed" );
	}

	/**
	 * User meta key recording that the impression Tracks event has fired for this user.
	 *
	 * @return string
	 */
	public function shown_meta_key(): string {
		return $this->resolve( __FUNCTION__, "wcpay_{$this->slug}_notice_shown" );
	}

	/**
	 * Transient key caching the result of the global eligibility check.
	 *
	 * @return string
	 */
	public function eligibility_transient_key(): string {
		return $this->resolve( __FUNCTION__, "wcpay_{$this->slug}_eligible" );
	}

	/**
	 * WP script handle for the React bundle.
	 *
	 * @return string
	 */
	public function script_handle(): string {
		return $this->resolve( __FUNCTION__, 'WCPAY_' . strtoupper( $this->slug ) . '_NOTICE' );
	}

	/**
	 * Build artifact base name in the dist/ directory.
	 *
	 * @return string
	 */
	public function dist_name(): string {
		return $this->resolve( __FUNCTION__, 'wc-payments-' . $this->dashed() . '-notice' );
	}

	/**
	 * DOM id the React component mounts into.
	 *
	 * @return string
	 */
	public function mount_div_id(): string {
		return $this->resolve( __FUNCTION__, 'wcpay-' . $this->dashed() . '-notice' );
	}

	/**
	 * Global JS variable name for the localized settings object.
	 *
	 * @return string
	 */
	public function localize_var_name(): string {
		$studly = str_replace( ' ', '', ucwords( str_replace( '_', ' ', $this->slug ) ) );
		return $this->resolve( __FUNCTION__, 'wcpay' . $studly . 'NoticeSettings' );
	}

	/**
	 * $_GET marker that triggers the CTA handler.
	 *
	 * @return string
	 */
	public function cta_query_arg(): string {
		return $this->resolve( __FUNCTION__, 'wcpay-' . $this->dashed() . '-cta' );
	}

	/**
	 * $_GET marker that triggers the dismiss handler.
	 *
	 * @return string
	 */
	public function hide_query_arg(): string {
		return $this->resolve( __FUNCTION__, 'wcpay-hide-' . $this->dashed() . '-notice' );
	}

	/**
	 * $_GET marker that triggers the snooze handler.
	 *
	 * @return string
	 */
	public function snooze_query_arg(): string {
		return $this->resolve( __FUNCTION__, 'wcpay-snooze-' . $this->dashed() . '-notice' );
	}

	/**
	 * Nonce action for the CTA URL.
	 *
	 * @return string
	 */
	public function cta_nonce_action(): string {
		return $this->resolve( __FUNCTION__, "wcpay_{$this->slug}_cta_nonce" );
	}

	/**
	 * Nonce action for the dismiss URL.
	 *
	 * @return string
	 */
	public function hide_nonce_action(): string {
		return $this->resolve( __FUNCTION__, "wcpay_hide_{$this->slug}_notice_nonce" );
	}

	/**
	 * Nonce action for the snooze URL.
	 *
	 * @return string
	 */
	public function snooze_nonce_action(): string {
		return $this->resolve( __FUNCTION__, "wcpay_snooze_{$this->slug}_notice_nonce" );
	}

	/**
	 * $_GET arg name carrying the CTA nonce.
	 *
	 * @return string
	 */
	public function cta_nonce_arg(): string {
		return $this->resolve( __FUNCTION__, "_wcpay_{$this->slug}_cta_nonce" );
	}

	/**
	 * $_GET arg name carrying the dismiss nonce.
	 *
	 * @return string
	 */
	public function hide_nonce_arg(): string {
		return $this->resolve( __FUNCTION__, "_wcpay_{$this->slug}_notice_nonce" );
	}

	/**
	 * $_GET arg name carrying the snooze nonce.
	 *
	 * @return string
	 */
	public function snooze_nonce_arg(): string {
		return $this->resolve( __FUNCTION__, "_wcpay_snooze_{$this->slug}_notice_nonce" );
	}

	/**
	 * Tracks event recorded the first time the user sees this notice.
	 *
	 * @return string
	 */
	public function shown_event_name(): string {
		return $this->resolve( __FUNCTION__, "wcpay_{$this->slug}_notice_shown" );
	}

	/**
	 * Tracks event recorded when the user dismisses this notice.
	 *
	 * @return string
	 */
	public function dismissed_event_name(): string {
		return $this->resolve( __FUNCTION__, "wcpay_{$this->slug}_notice_dismissed" );
	}

	/**
	 * Tracks event recorded when the user snoozes this notice.
	 *
	 * @return string
	 */
	public function snoozed_event_name(): string {
		return $this->resolve( __FUNCTION__, "wcpay_{$this->slug}_notice_snoozed" );
	}

	/**
	 * Tracks event recorded when the user clicks the CTA.
	 *
	 * @return string
	 */
	public function cta_event_name(): string {
		return $this->resolve( __FUNCTION__, "wcpay_{$this->slug}_notice_cta_clicked" );
	}

	/**
	 * Override for $key if supplied, else the derived default.
	 *
	 * @param string $key     Getter name (passed as __FUNCTION__ by each getter).
	 * @param string $derived Slug-derived default.
	 * @return string
	 */
	private function resolve( string $key, string $derived ): string {
		return $this->overrides[ $key ] ?? $derived;
	}

	/**
	 * Kebab-cased slug.
	 *
	 * @return string
	 */
	private function dashed(): string {
		return str_replace( '_', '-', $this->slug );
	}
}
