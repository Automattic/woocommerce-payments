<?php
/**
 * Installs the payment evidence schema independently of the plugin version marker.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service;

/** A failed installation remains eligible for retry on the next startup. */
class PaymentEventSchema {
	const VERSION_OPTION = 'wcpay_payment_event_schema_version';
	const VERSION        = '1';

	/**
	 * Event storage.
	 *
	 * @var PaymentEventRepository
	 */
	private $repository;

	/**
	 * Database connection for conditional marker writes.
	 *
	 * @var \wpdb
	 */
	private $db;

	/**
	 * Blog identity bound at construction.
	 *
	 * @var int
	 */
	private $blog_id;


	/**
	 * Construct the installer.
	 *
	 * @param PaymentEventRepository $repository Event storage.
	 * @param \wpdb                  $db Database connection.
	 */
	public function __construct( PaymentEventRepository $repository, \wpdb $db ) {
		$this->repository = $repository;
		$this->db         = $db;
		$this->blog_id    = get_current_blog_id();
	}

	/**
	 * Install missing schema; existing evidence access validates compatibility itself.
	 *
	 * The fast path avoids metadata queries on every storefront request. A newer or
	 * unrecognized marker is never downgraded. No payment retrieval is scheduled here.
	 *
	 * @return bool Whether installation succeeded or this version was already recorded.
	 */
	public function maybe_install(): bool {
		if ( get_current_blog_id() !== $this->blog_id ) {
			return false;
		}
		$version = get_option( self::VERSION_OPTION, '0' );
		if ( self::VERSION === $version ) {
			return true;
		}
		if ( '0' !== $version || ! $this->repository->create_schema() ) {
			return false;
		}
		if ( get_current_blog_id() !== $this->blog_id ) {
			return false;
		}
		// Conditional writes cannot overwrite a newer installer's marker.
		$this->db->query( $this->db->prepare( "INSERT IGNORE INTO {$this->db->options} (option_name,option_value,autoload) VALUES (%s,%s,'no')", self::VERSION_OPTION, self::VERSION ) );
		$this->db->query( $this->db->prepare( "UPDATE {$this->db->options} SET option_value=%s, autoload='no' WHERE option_name=%s AND BINARY option_value='0'", self::VERSION, self::VERSION_OPTION ) );
		wp_cache_delete( self::VERSION_OPTION, 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		return self::VERSION === get_option( self::VERSION_OPTION, '0' );
	}
}
