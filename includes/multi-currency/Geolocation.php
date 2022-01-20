<?php
/**
 * WooCommerce Payments Multi-Currency Geolocation Class
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\MultiCurrency;

use WC_Payments_Localization_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Geolocation.
 */
class Geolocation {
	/**
	 * WC_Payments_Localization_Service instance.
	 *
	 * @var WC_Payments_Localization_Service
	 */
	protected $localization_service;

	/**
	 * Constructor.
	 *
	 * @param WC_Payments_Localization_Service $localization_service The Localization Service instance.
	 */
	public function __construct( WC_Payments_Localization_Service $localization_service ) {
		$this->localization_service = $localization_service;
	}

	/**
	 * Gets the customer's currency based on their location.
	 *
	 * @return string|null Currency code or null if not found.
	 */
	public function get_currency_by_customer_location() {
		$country = $this->get_country_by_customer_location();

		return $this->localization_service->get_country_locale_data( $country )['currency_code'] ?? null;
	}

	/**
	 * Gets the customer's country based on their location.
	 *
	 * @return string Country code.
	 */
	public function get_country_by_customer_location() {
		$country = $this->geolocate_customer();

		if ( $country ) {
			// Once we have a location, ensure it's valid, otherwise fallback to the default country.
			$allowed_country_codes = WC()->countries->get_allowed_countries();
			if ( ! array_key_exists( $country, $allowed_country_codes ) ) {
				$country = null;
			}
		}

		if ( ! $country ) {
			$default_location = get_option( 'woocommerce_default_country', '' );
			$location         = wc_format_country_state_string( apply_filters( 'woocommerce_customer_default_location', $default_location ) );
			$country          = $location['country'];
		}

		return $country;
	}

	/**
	 * Attempts to guess the customer's country based on their IP.
	 *
	 * @return string|null Country code, or NULL if it couldn't be determined.
	 */
	private function geolocate_customer() {
		// Exclude common bots from geolocation by user agent.
		$ua = wc_get_user_agent();
		if ( stripos( $ua, 'bot' ) !== false || stripos( $ua, 'spider' ) !== false || stripos( $ua, 'crawl' ) !== false ) {
			return null;
		}

		$geolocation = \WC_Geolocation::geolocate_ip( '', true, true );
		return $geolocation['country'] ?? null;
	}
}
