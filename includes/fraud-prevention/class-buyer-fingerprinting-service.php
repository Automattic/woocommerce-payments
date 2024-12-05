<?php
/**
 * Class Buyer_Fingerprinting_Service
 *
 * @package WCPay\Fraud_Prevention
 */

namespace WCPay\Fraud_Prevention;

use WC_Geolocation;

/**
 * Class Buyer_Fingerprinting_Service
 */
class Buyer_Fingerprinting_Service {
	/**
	 * Singleton instance.
	 *
	 * @var Buyer_Fingerprinting_Service
	 */
	private static $instance;

	/**
	 * Returns singleton instance.
	 *
	 * @return Buyer_Fingerprinting_Service
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Sets a instance to be used in request cycle.
	 * Introduced primarily for supporting unit tests.
	 *
	 * @param Buyer_Fingerprinting_Service|null $instance Instance of self.
	 */
	public static function set_instance( ?self $instance = null ) {
		self::$instance = $instance;
	}

	/**
	 * Hashes customer data for the fraud prevention.
	 *
	 * @param string $data The data you want to hash.
	 *
	 * @return string Hashed data.
	 */
	public function hash_data_for_fraud_prevention( string $data ): string {
		return hash( 'sha512', $data, false );
	}

	/**
	 * Returns fraud prevention data for an order.
	 *
	 * @param string $fingerprint User fingerprint.
	 *
	 * @return array An array of hashed data for an order.
	 */
	public function get_hashed_data_for_customer( $fingerprint ): array {
		global $wp;
		$order_items_count = WC()->cart ? intval( WC()->cart->get_cart_contents_count() ) : null;
		$order_id          = null;
		if ( isset( $wp->query_vars['order-pay'] ) ) {
			$order_id = absint( $wp->query_vars['order-pay'] );
		} elseif ( isset( $_POST['wcpay_order_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$order_id = absint( $_POST['wcpay_order_id'] ); // phpcs:ignore WordPress.Security.NonceVerification
		}
		if ( ! $order_items_count && 0 < $order_id ) {
			$order = wc_get_order( $order_id );
			if ( $order ) {
				$order_items_count = $order->get_item_count();
			}
		}

		// According to https://www.php.net/manual/en/function.array-filter.php#111091
		// Applying "strlen" as the callback function will remove `false`, `null` and empty strings, but not "0" values.
		return array_filter(
			[
				'fraud_prevention_data_shopper_ip_hash' => $this->hash_data_for_fraud_prevention( WC_Geolocation::get_ip_address() ),
				'fraud_prevention_data_shopper_ua_hash' => $fingerprint,
				'fraud_prevention_data_ip_country'      => WC_Geolocation::geolocate_ip( '', true )['country'],
				'fraud_prevention_data_cart_contents'   => $order_items_count,
			],
			'strlen'
		);
	}
}
