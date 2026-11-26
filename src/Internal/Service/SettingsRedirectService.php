<?php
/**
 * Class SettingsRedirectService
 *
 * @package WooCommerce\Payments
 */

declare( strict_types=1 );

namespace WCPay\Internal\Service;

use WC_Payments;
use WCPay\Internal\Proxy\LegacyProxy;

/**
 * Handles the stable, store-agnostic link to the WooPayments settings page:
 * `{home_url}/?woopayments-settings-redirect[&method=<payment method ID>][&<flag>=<value>...]`.
 *
 * The link lives on the storefront (like the VAT details redirect), so the same link works for
 * every store and wp-admin sends logged-out visitors through the login page first.
 *
 * - `method` scrolls the settings page to that payment method's row. Unknown IDs are dropped.
 * - Every other query arg is a flag for the settings page, e.g. to highlight changed rates or
 *   conditions. Flags are forwarded with the `woopayments-flag-` prefix, so they cannot override
 *   the settings page's own query args. They only select what the page shows and must never
 *   change state, as anyone can craft the link.
 */
class SettingsRedirectService {
	/**
	 * Query arg that triggers the redirect.
	 */
	public const REDIRECT_QUERY_ARG = 'woopayments-settings-redirect';

	/**
	 * Query arg holding the payment method ID to scroll to.
	 */
	public const METHOD_QUERY_ARG = 'method';

	/**
	 * Prefix added to forwarded flags on the settings URL. Mirrored by `settingsRedirectFlagPrefix` in `client/settings/settings-redirect-flags.ts`.
	 */
	public const FLAG_PREFIX = 'woopayments-flag-';

	/**
	 * Flags forwarded at most, to keep crafted links from producing oversized URLs.
	 */
	private const MAX_FLAGS = 10;

	/**
	 * Maximum length of a flag value.
	 */
	private const MAX_FLAG_VALUE_LENGTH = 100;

	/**
	 * Legacy proxy.
	 *
	 * @var LegacyProxy
	 */
	private $legacy_proxy;

	/**
	 * SettingsRedirectService constructor.
	 *
	 * @param LegacyProxy $legacy_proxy Legacy proxy.
	 */
	public function __construct( LegacyProxy $legacy_proxy ) {
		$this->legacy_proxy = $legacy_proxy;
	}

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function init_hooks(): void {
		add_action( 'template_redirect', [ $this, 'maybe_redirect' ], 1 );
	}

	/**
	 * Redirects the stable link to the WooPayments settings page.
	 *
	 * @return void
	 */
	public function maybe_redirect(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only redirect, no state change.
		if ( ! isset( $_GET[ self::REDIRECT_QUERY_ARG ] ) ) {
			return;
		}

		if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || wp_doing_ajax() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only redirect; values are sanitized below.
		$query = wp_unslash( $_GET );

		$method = $query[ self::METHOD_QUERY_ARG ] ?? '';
		unset( $query[ self::REDIRECT_QUERY_ARG ], $query[ self::METHOD_QUERY_ARG ] );

		wp_safe_redirect( $this->get_settings_url( is_string( $method ) ? $method : '', $query ) );
		exit;
	}

	/**
	 * Returns the WooPayments settings URL.
	 *
	 * @param string $payment_method Payment method ID to scroll to. Ignored unless WooPayments registers it.
	 * @param array  $flags          Flags for the settings page, keyed by name. Names are sanitized with
	 *                               `sanitize_key()`; empty values become `1`; non-string values are dropped.
	 *
	 * @return string
	 */
	public function get_settings_url( string $payment_method = '', array $flags = [] ): string {
		$args = [
			'page'    => 'wc-settings',
			'tab'     => 'checkout',
			'section' => 'woocommerce_payments',
		];

		if ( $this->is_known_payment_method( $payment_method ) ) {
			$args['anchor'] = rawurlencode( '#payment-method-' . $payment_method );
		}

		foreach ( $this->sanitize_flags( $flags ) as $name => $value ) {
			$args[ self::FLAG_PREFIX . $name ] = rawurlencode( $value );
		}

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Whether the ID belongs to a payment method WooPayments registers a gateway for.
	 *
	 * @param string $payment_method Payment method ID.
	 *
	 * @return bool
	 */
	private function is_known_payment_method( string $payment_method ): bool {
		if ( '' === $payment_method ) {
			return false;
		}

		$gateway_map = $this->legacy_proxy->call_static( WC_Payments::class, 'get_payment_gateway_map' );

		return is_array( $gateway_map ) && array_key_exists( $payment_method, $gateway_map );
	}

	/**
	 * Sanitizes flag names and values, dropping anything that cannot be forwarded.
	 *
	 * @param array $flags Raw flags.
	 *
	 * @return array<string, string>
	 */
	private function sanitize_flags( array $flags ): array {
		$sanitized = [];

		foreach ( $flags as $name => $value ) {
			if ( count( $sanitized ) >= self::MAX_FLAGS ) {
				break;
			}

			$name = sanitize_key( (string) $name );
			if ( '' === $name || ! ( is_string( $value ) || is_int( $value ) || is_bool( $value ) ) ) {
				continue;
			}

			$value = is_bool( $value ) ? ( $value ? '1' : '0' ) : sanitize_text_field( (string) $value );
			$value = mb_substr( $value, 0, self::MAX_FLAG_VALUE_LENGTH );

			$sanitized[ $name ] = '' === $value ? '1' : $value;
		}

		return $sanitized;
	}
}
