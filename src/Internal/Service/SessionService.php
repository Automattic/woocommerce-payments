<?php
/**
 * SessionService class.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service;

use WC_Session;
use WCPay\Internal\Proxy\LegacyProxy;

/**
 * Session service. Use as a translation layer between `src` classes and Woo core WC_Session class.
 */
class SessionService {
	/**
	 * Legacy Proxy.
	 *
	 * @var LegacyProxy
	 */
	private $legacy_proxy;

	/**
	 * Constructor.
	 *
	 * @param LegacyProxy $legacy_proxy Legacy proxy instance.
	 */
	public function __construct( LegacyProxy $legacy_proxy ) {
		$this->legacy_proxy = $legacy_proxy;
	}

	/**
	 * Getter.
	 *
	 * @param string     $key     Session key.
	 * @param mixed|null $default Default value to return if key is not set.
	 *
	 * @return mixed
	 */
	public function get( string $key, $default = null ) {
		if ( $this->has_wc_session() ) {
			return $this->get_wc_session()->get( $key, $default );
		} else {
			return $default;
		}
	}

	/**
	 * Setter.
	 *
	 * @param string $key   Session key.
	 * @param mixed  $value Value to set.
	 *
	 * @return void
	 */
	public function set( string $key, $value ): void {
		if ( $this->has_wc_session() ) {
			$this->get_wc_session()->set( $key, $value );
		}
	}

	/**
	 * Checks whether the WC_Session has been initialized.
	 *
	 * @return bool Whether the WC_Session has been initialized.
	 */
	private function has_wc_session(): bool {
		return $this->legacy_proxy->call_function( 'WC' )->session instanceof WC_Session;
	}

	/**
	 * Gets the WC_Session instance.
	 *
	 * @return WC_Session|null
	 */
	private function get_wc_session(): ?WC_Session {
		return $this->legacy_proxy->call_function( 'WC' )->session;
	}
}
