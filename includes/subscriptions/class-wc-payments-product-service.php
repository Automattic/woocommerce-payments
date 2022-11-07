<?php
/**
 * Class WC_Payments_Product_Service
 *
 * @package WooCommerce\Payments
 */

use WCPay\Exceptions\API_Exception;
use WCPay\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Class handling any subscription product functionality
 */
class WC_Payments_Product_Service {

	use WC_Payments_Subscriptions_Utilities;

	/**
	 * The product meta key used to store the product data we last sent to WC Pay as a hash. Used to compare current WC product data with WC Pay data.
	 *
	 * @const string
	 */
	const PRODUCT_HASH_KEY = '_wcpay_product_hash';

	/**
	 * The live product meta key used to store the product's ID in WC Pay.
	 *
	 * @const string
	 */
	const LIVE_PRODUCT_ID_KEY = '_wcpay_product_id_live';

	/**
	 * The testmode product meta key used to store the product's ID in WC Pay.
	 *
	 * @const string
	 */
	const TEST_PRODUCT_ID_KEY = '_wcpay_product_id_test';

	/**
	 * The product price meta key used to store the price data we last sent to WC Pay as a hash. Used to compare current WC product price data with WC Pay data.
	 *
	 * @const string
	 */
	const PRICE_HASH_KEY = '_wcpay_product_price_hash';

	/**
	 * The product meta key used to store the live product's WC Pay Price object ID.
	 *
	 * @const string
	 */
	const LIVE_PRICE_ID_KEY = '_wcpay_product_price_id_live';

	/**
	 * The product meta key used to store the testmode product's WC Pay Price object ID.
	 *
	 * @const string
	 */
	const TEST_PRICE_ID_KEY = '_wcpay_product_price_id_test';

	/**
	 * Client for making requests to the WooCommerce Payments API
	 *
	 * @var WC_Payments_API_Client
	 */
	private $payments_api_client;

	/**
	 * The list of products we need to update at the end of each request.
	 *
	 * @var array
	 */
	private $products_to_update = [];

	/**
	 * Constructor.
	 *
	 * @param WC_Payments_API_Client $payments_api_client Payments API client.
	 */
	public function __construct( WC_Payments_API_Client $payments_api_client ) {
		$this->payments_api_client = $payments_api_client;

		add_action( 'shutdown', [ $this, 'create_or_update_products' ] );
		add_action( 'wp_trash_post', [ $this, 'maybe_archive_product' ] );
		add_action( 'untrashed_post', [ $this, 'maybe_unarchive_product' ] );
		add_filter( 'woocommerce_duplicate_product_exclude_meta', [ $this, 'exclude_meta_wcpay_product' ] );

		$this->add_product_update_listeners();
	}

	/**
	 * Gets the WC Pay product hash associated with a WC product.
	 *
	 * @param WC_Product $product The product to get the hash for.
	 * @return string             The product's hash or an empty string.
	 */
	public static function get_wcpay_product_hash( WC_Product $product ) : string {
		return $product->get_meta( self::PRODUCT_HASH_KEY, true );
	}

	/**
	 * Gets the WC Pay product ID associated with a WC product.
	 *
	 * @param WC_Product $product   The product to get the WC Pay ID for.
	 * @param bool|null  $test_mode Is WC Pay in test/dev mode.
	 *
	 * @return string             The WC Pay product ID or an empty string.
	 */
	public function get_wcpay_product_id( WC_Product $product, $test_mode = null ) : string {
		// If the subscription product doesn't have a WC Pay product ID, create one.
		if ( ! self::has_wcpay_product_id( $product, $test_mode ) ) {
			$is_current_environment = null === $test_mode || WC_Payments::mode()->is_test() === $test_mode;

			// Only create a new wcpay product if we're trying to fetch a wcpay product ID in the current environment.
			if ( $is_current_environment ) {
				WC_Payments_Subscriptions::get_product_service()->create_product( $product );
			}
		}

		return $product->get_meta( self::get_wcpay_product_id_option( $test_mode ), true );
	}

	/**
	 * Gets the WC Pay product ID associated with a WC product.
	 *
	 * @param string $type The item type to create a product for.
	 * @return string       The item's WCPay product id.
	 */
	public function get_wcpay_product_id_for_item( string $type ) : string {
		$sanitized_type  = self::sanitize_option_key( $type );
		$option_key_name = self::get_wcpay_product_id_option() . '_' . $sanitized_type;
		if ( ! get_option( $option_key_name ) ) {
			$this->create_product_for_item_type( $sanitized_type );
		}
		return get_option( $option_key_name );
	}

	/**
	 * Sanitize option key string to replace space with underscore, and remove special characters.
	 *
	 * @param string $type Non sanitized input.
	 * @return string       Sanitized output.
	 */
	public static function sanitize_option_key( string $type ) {
		return sanitize_key( str_replace( ' ', '_', trim( $type ) ) );
	}

	/**
	 * Check if the WC product has a WC Pay product ID.
	 *
	 * @param WC_Product $product   The product to get the WC Pay ID for.
	 * @param bool|null  $test_mode Is WC Pay in test/dev mode.
	 *
	 * @return bool                 The WC Pay product ID or an empty string.
	 */
	public static function has_wcpay_product_id( WC_Product $product, $test_mode = null ) : bool {
		return (bool) $product->get_meta( self::get_wcpay_product_id_option( $test_mode ) );
	}

	/**
	 * Prevents duplicate WC Pay product IDs and hashes when duplicating a subscription product.
	 *
	 * @param array $meta_keys The keys to exclude from the duplicate.
	 * @return array Keys to exclude.
	 */
	public static function exclude_meta_wcpay_product( $meta_keys ) {
		return array_merge(
			$meta_keys,
			[
				self::PRODUCT_HASH_KEY,
				self::LIVE_PRODUCT_ID_KEY,
				self::TEST_PRODUCT_ID_KEY,
				self::PRICE_HASH_KEY,
				self::LIVE_PRICE_ID_KEY,
				self::TEST_PRICE_ID_KEY,
			]
		);
	}

	/**
	 * Schedules a subscription product to be created or updated in WC Pay on shutdown.
	 *
	 * @since 3.2.0
	 *
	 * @param int $product_id The ID of the product to handle.
	 */
	public function maybe_schedule_product_create_or_update( int $product_id ) {

		// Skip products which have already been scheduled or aren't subscriptions.
		$product = wc_get_product( $product_id );
		if ( ! $product || isset( $this->products_to_update[ $product_id ] ) || ! WC_Subscriptions_Product::is_subscription( $product ) ) {
			return;
		}

		foreach ( $this->get_products_to_update( $product ) as $product_to_update ) {
			// Skip products already scheduled.
			if ( isset( $this->products_to_update[ $product_to_update->get_id() ] ) ) {
				continue;
			}

			// Skip product variations that don't have a price set.
			if ( $product_to_update->is_type( 'subscription_variation' ) && '' === $product_to_update->get_price() ) {
				continue;
			}

			if ( ! self::has_wcpay_product_id( $product_to_update ) || $this->product_needs_update( $product_to_update ) ) {
				$this->products_to_update[ $product_to_update->get_id() ] = $product_to_update->get_id();
			}
		}
	}

	/**
	 * Creates and updates all products which have been scheduled for an update.
	 *
	 * Hooked onto shutdown so all products which have been changed in the current request can be updated once.
	 *
	 * @since 3.2.0
	 */
	public function create_or_update_products() {
		foreach ( $this->products_to_update as $product_id ) {
			$product = wc_get_product( $product_id );

			if ( ! $product ) {
				continue;
			}

			$this->update_products( $product );
		}
	}

	/**
	 * Creates a product in WC Pay.
	 *
	 * @param WC_Product $product The product to create.
	 */
	public function create_product( WC_Product $product ) {
		try {
			$product_data = $this->get_product_data( $product );

			// Validate that we have enough data to create the product.
			$this->validate_product_data( $product_data );

			$wcpay_product = $this->payments_api_client->create_product( $product_data );

			$this->remove_product_update_listeners();
			$this->set_wcpay_product_hash( $product, $this->get_product_hash( $product ) );
			$this->set_wcpay_product_id( $product, $wcpay_product['wcpay_product_id'] );
			$this->add_product_update_listeners();
		} catch ( \Exception $e ) {
			Logger::log( sprintf( 'There was a problem creating the product #%s in WC Pay: %s', $product->get_id(), $e->getMessage() ) );
		}
	}

	/**
	 * Create a generic item product in WC Pay.
	 *
	 * @param string $type The item type to create a product for.
	 */
	public function create_product_for_item_type( string $type ) {
		try {
			$wcpay_product = $this->payments_api_client->create_product(
				[
					'description' => 'N/A',
					'name'        => ucfirst( $type ),
				]
			);

			update_option( self::get_wcpay_product_id_option() . '_' . $type, $wcpay_product['wcpay_product_id'] );
		} catch ( API_Exception $e ) {
			Logger::log( 'There was a problem creating the product on WCPay Server: ' . $e->getMessage() );
		}
	}

	/**
	 * Updates related products in WC Pay when a WC Product is updated.
	 *
	 * @param WC_Product $product The product to update.
	 */
	public function update_products( WC_Product $product ) {
		if ( ! WC_Subscriptions_Product::is_subscription( $product ) ) {
			return;
		}

		$wcpay_product_ids = $this->get_all_wcpay_product_ids( $product );
		$test_mode         = WC_Payments::mode()->is_test();

		// If the current environment doesn't have a product ID, make sure we create one.
		if ( ! isset( $wcpay_product_ids[ $test_mode ? 'test' : 'live' ] ) ) {
			$this->create_product( $product );
		}

		// Return when there's no products to update.
		if ( empty( $wcpay_product_ids ) ) {
			return;
		}

		if ( ! $this->product_needs_update( $product ) ) {
			return;
		}

		$data = $this->get_product_data( $product );

		$this->remove_product_update_listeners();

		try {
			// Validate that we have enough data to create the product.
			$this->validate_product_data( $data );

			// Update all versions of WCPay Products that need updating.
			foreach ( $wcpay_product_ids as $environment => $wcpay_product_id ) {
				$data['test_mode'] = 'live' !== $environment;
				$this->payments_api_client->update_product( $wcpay_product_id, $data );
			}

			$this->set_wcpay_product_hash( $product, $this->get_product_hash( $product ) );
		} catch ( \Exception $e ) {
			Logger::log( sprintf( 'There was a problem updating the product #%s in WC Pay: %s', $product->get_id(), $e->getMessage() ) );
		}

		$this->add_product_update_listeners();
	}

	/**
	 * Archives a subscription product in WC Pay.
	 *
	 * @since 3.2.0
	 *
	 * @param int $post_id The ID of the post to handle. Only subscription product IDs will be archived in WC Pay.
	 */
	public function maybe_archive_product( int $post_id ) {
		$product = wc_get_product( $post_id );

		if ( $product && WC_Subscriptions_Product::is_subscription( $product ) ) {
			foreach ( $this->get_products_to_update( $product ) as $product ) {
				$this->archive_product( $product );
			}
		}
	}

	/**
	 * Unarchives a subscription product in WC Pay.
	 *
	 * @since 3.2.0
	 *
	 * @param int $post_id The ID of the post to handle. Only Subscription product post IDs will be unarchived in WC Pay.
	 */
	public function maybe_unarchive_product( int $post_id ) {
		$product = wc_get_product( $post_id );

		if ( $product && WC_Subscriptions_Product::is_subscription( $product ) ) {
			foreach ( $this->get_products_to_update( $product ) as $product ) {
				$this->unarchive_product( $product );
			}
		}
	}

	/**
	 * Archives all related WCPay products (live and test) when a product is trashed/deleted in WC.
	 *
	 * @param WC_Product $product The product to archive.
	 */
	public function archive_product( WC_Product $product ) {
		$wcpay_product_ids = $this->get_all_wcpay_product_ids( $product );

		if ( empty( $wcpay_product_ids ) ) {
			return;
		}

		foreach ( $wcpay_product_ids as $environment => $wcpay_product_id ) {
			try {
				$this->delete_all_wcpay_price_ids( $product );

				$this->payments_api_client->update_product(
					$wcpay_product_id,
					[
						'active'    => 'false',
						'test_mode' => 'live' !== $environment,
					]
				);
			} catch ( API_Exception $e ) {
				Logger::log( 'There was a problem archiving the ' . $environment . ' product in WC Pay: ' . $e->getMessage() );
			}
		}
	}

	/**
	 * Unarchives all related WCPay products (live and test) when a product in WC is untrashed.
	 *
	 * @param WC_Product $product The product unarchive.
	 */
	public function unarchive_product( WC_Product $product ) {
		$wcpay_product_ids = $this->get_all_wcpay_product_ids( $product );

		if ( empty( $wcpay_product_ids ) ) {
			return;
		}

		foreach ( $wcpay_product_ids as $environment => $wcpay_product_id ) {
			try {
				$this->payments_api_client->update_product(
					$wcpay_product_id,
					[
						'active'    => 'true',
						'test_mode' => 'live' !== $environment,
					]
				);
			} catch ( API_Exception $e ) {
				Logger::log( 'There was a problem unarchiving the ' . $environment . 'product in WC Pay: ' . $e->getMessage() );
			}
		}
	}

	/**
	 * Archives a WC Pay price object.
	 *
	 * @param string    $wcpay_price_id The price object's ID to archive.
	 * @param bool|null $test_mode      Is WC Pay in test/dev mode.
	 */
	public function archive_price( string $wcpay_price_id, $test_mode = null ) {
		$data = [ 'active' => 'false' ];

		if ( null !== $test_mode ) {
			$data['test_mode'] = $test_mode;
		}

		$this->payments_api_client->update_price( $wcpay_price_id, $data );
	}

	/**
	 * Prevents the subscription interval to be greater than 1 for yearly subscriptions.
	 *
	 * @param int $product_id Post ID of the product.
	 */
	public function limit_subscription_product_intervals( $product_id ) {
		if ( $this->is_subscriptions_plugin_active() ) {
			return;
		}

		// Skip products that aren't subscriptions.
		$product = wc_get_product( $product_id );

		if (
			! $product ||
			! WC_Subscriptions_Product::is_subscription( $product ) ||
			empty( $_POST['_wcsnonce'] ) ||
			! wp_verify_nonce( sanitize_key( $_POST['_wcsnonce'] ), 'wcs_subscription_meta' )
		) {
			return;
		}

		// If we don't have both the period and the interval, there's nothing to do here.
		if ( empty( $_REQUEST['_subscription_period'] ) || empty( $_REQUEST['_subscription_period_interval'] ) ) {
			return;
		}

		$period   = sanitize_text_field( wp_unslash( $_REQUEST['_subscription_period'] ) );
		$interval = absint( wp_unslash( $_REQUEST['_subscription_period_interval'] ) );

		// Prevent WC Subs Core from saving the interval when it's invalid.
		if ( ! $this->is_valid_billing_cycle( $period, $interval ) ) {
			$new_interval                              = $this->get_period_interval_limit( $period );
			$_REQUEST['_subscription_period_interval'] = (string) $new_interval;

			/* translators: %1$s Opening strong tag, %2$s Closing strong tag, %3$s The subscription renewal interval (every x time) */
			wcs_add_admin_notice( sprintf( __( '%1$sThere was an issue saving your product!%2$s A subscription product\'s billing period cannot be longer than one year. We have updated this product to renew every %3$s.', 'woocommerce-payments' ), '<strong>', '</strong>', wcs_get_subscription_period_strings( $new_interval, $period ) ), 'error' );
		}
	}

	/**
	 * Prevents the subscription interval to be greater than 1 for yearly subscription variations.
	 *
	 * @param int $product_id Post ID of the variation.
	 * @param int $index Variation index in the incoming array.
	 */
	public function limit_subscription_variation_intervals( $product_id, $index ) {
		if ( $this->is_subscriptions_plugin_active() ) {
			return;
		}

		// Skip products that aren't subscriptions.
		$product           = wc_get_product( $product_id );
		$admin_notice_sent = false;

		if (
			! $product ||
			! WC_Subscriptions_Product::is_subscription( $product ) ||
			empty( $_POST['_wcsnonce_save_variations'] ) ||
			! wp_verify_nonce( sanitize_key( $_POST['_wcsnonce_save_variations'] ), 'wcs_subscription_variations' )
		) {
			return;
		}

		// If we don't have both the period and the interval, there's nothing to do here.
		if ( empty( $_POST['variable_subscription_period'][ $index ] ) || empty( $_POST['variable_subscription_period_interval'][ $index ] ) ) {
			return;
		}

		$period   = sanitize_text_field( wp_unslash( $_POST['variable_subscription_period'][ $index ] ) );
		$interval = absint( wp_unslash( $_POST['variable_subscription_period_interval'][ $index ] ) );

		// Prevent WC Subs Core from saving the interval when it's invalid.
		if ( ! $this->is_valid_billing_cycle( $period, $interval ) ) {
			$new_interval = $this->get_period_interval_limit( $period );
			$_POST['variable_subscription_period_interval'][ $index ] = (string) $new_interval;

			if ( false === $admin_notice_sent ) {
				$admin_notice_sent = true;

				/* translators: %1$s Opening strong tag, %2$s Closing strong tag */
				wcs_add_admin_notice( sprintf( __( '%1$sThere was an issue saving your variations!%2$s A subscription product\'s billing period cannot be longer than one year. We have updated one or more of this product\'s variations to renew every %3$s.', 'woocommerce-payments' ), '<strong>', '</strong>', wcs_get_subscription_period_strings( $new_interval, $period ) ), 'error' );
			}
		}
	}

	/**
	 * Attaches the callbacks used to update product changes in WC Pay.
	 */
	private function add_product_update_listeners() {
		// This needs to run before WC_Subscriptions_Admin::save_subscription_meta(), which has a priority of 11.
		add_action( 'save_post', [ $this, 'limit_subscription_product_intervals' ], 10 );
		// This needs to run before WC_Subscriptions_Admin::save_product_variation(), which has a priority of 20.
		add_action( 'woocommerce_save_product_variation', [ $this, 'limit_subscription_variation_intervals' ], 19, 2 );

		add_action( 'save_post', [ $this, 'maybe_schedule_product_create_or_update' ], 12 );
		add_action( 'woocommerce_save_product_variation', [ $this, 'maybe_schedule_product_create_or_update' ], 30 );
	}

	/**
	 * Removes the callbacks used to update product changes in WC Pay.
	 */
	private function remove_product_update_listeners() {
		remove_action( 'save_post', [ $this, 'limit_subscription_product_intervals' ], 10 );
		remove_action( 'woocommerce_save_product_variation', [ $this, 'limit_subscription_variation_intervals' ], 19 );

		remove_action( 'save_post', [ $this, 'maybe_schedule_product_create_or_update' ], 12 );
		remove_action( 'woocommerce_save_product_variation', [ $this, 'maybe_schedule_product_create_or_update' ], 30 );
	}

	/**
	 * Gets product data relevant to WC Pay from a WC product.
	 *
	 * @param WC_Product $product The product to get data from.
	 * @return array
	 */
	private function get_product_data( WC_Product $product ) : array {
		return [
			'description' => $product->get_description() ? $product->get_description() : 'N/A',
			'name'        => $product->get_name(),
		];
	}

	/**
	 * Gets the products to update from a given product.
	 *
	 * If applicable, returns the product's variations otherwise returns the product by itself.
	 *
	 * @param WC_Product|WC_Product_Variable $product The product.
	 *
	 * @return array The products to update.
	 */
	private function get_products_to_update( WC_Product $product ) : array {
		return $product->is_type( 'variable-subscription' ) ? $product->get_available_variations( 'object' ) : [ $product ];
	}

	/**
	 * Gets a hash of the product's name and description.
	 * Used to compare WC changes with WC Pay data.
	 *
	 * @param WC_Product $product The product to generate the hash for.
	 * @return string             The product's hash.
	 */
	private function get_product_hash( WC_Product $product ) : string {
		return md5( implode( $this->get_product_data( $product ) ) );
	}

	/**
	 * Checks if a product needs to be updated in WC Pay.
	 *
	 * @param WC_Product $product The product to check updates for.
	 *
	 * @return bool Whether the product needs to be update in WC Pay.
	 */
	private function product_needs_update( WC_Product $product ) : bool {
		return $this->get_product_hash( $product ) !== $this->get_wcpay_product_hash( $product );
	}

	/**
	 * Sets a WC Pay product hash on a WC product.
	 *
	 * @param WC_Product $product The product to set the WC Pay product hash for.
	 * @param string     $value   The WC Pay product hash.
	 */
	private function set_wcpay_product_hash( WC_Product $product, string $value ) {
		$product->update_meta_data( self::PRODUCT_HASH_KEY, $value );
		$product->save();
	}

	/**
	 * Sets a WC Pay product ID on a WC product.
	 *
	 * @param WC_Product $product The product to set the WC Pay ID for.
	 * @param string     $value   The WC Pay product ID.
	 */
	private function set_wcpay_product_id( WC_Product $product, string $value ) {
		$product->update_meta_data( self::get_wcpay_product_id_option(), $value );
		$product->save();
	}

	/**
	 * Returns the name of the product id option meta, taking test mode into account.
	 *
	 * @param bool|null $test_mode Is WC Pay in test/dev mode.
	 *
	 * @return string The WCPay product ID meta key/option name.
	 */
	public static function get_wcpay_product_id_option( $test_mode = null ) : string {
		$test_mode = null === $test_mode ? WC_Payments::mode()->is_test() : $test_mode;
		return $test_mode ? self::TEST_PRODUCT_ID_KEY : self::LIVE_PRODUCT_ID_KEY;
	}

	/**
	 * Returns the name of the price id option meta, taking test mode into account.
	 *
	 * @param bool|null $test_mode Is WC Pay in test/dev mode.
	 *
	 * @return string The price hash option name.
	 */
	public static function get_wcpay_price_id_option( $test_mode = null ) : string {
		$test_mode = null === $test_mode ? WC_Payments::mode()->is_test() : $test_mode;
		return $test_mode ? self::TEST_PRICE_ID_KEY : self::LIVE_PRICE_ID_KEY;
	}

	/**
	 * Gets all WCPay Product IDs linked to a WC Product (live and testmode products).
	 *
	 * @param WC_Product $product The product to fetch WCPay product IDs for.
	 *
	 * @return array Live and test WCPay Product IDs if they exist.
	 */
	private function get_all_wcpay_product_ids( WC_Product $product ) {
		$environment_product_ids = [
			'live' => self::has_wcpay_product_id( $product, false ) ? $this->get_wcpay_product_id( $product, false ) : null,
			'test' => self::has_wcpay_product_id( $product, true ) ? $this->get_wcpay_product_id( $product, true ) : null,
		];

		return array_filter( $environment_product_ids );
	}

	/**
	 * Returns whether the billing cycle is valid, given its period and interval.
	 *
	 * @param string $period Cycle period.
	 * @param int    $interval Cycle interval.
	 * @return boolean
	 */
	public function is_valid_billing_cycle( $period, $interval ) {
		$interval_limit = $this->get_period_interval_limit( $period );

		// A cycle is valid when we have a defined limit, and the given interval isn't 0 nor greater than the limit.
		return $interval_limit && ! empty( $interval ) && $interval <= $interval_limit;
	}

	/**
	 * Returns the interval limit for the given period.
	 *
	 * @param string $period The period to get the interval limit for.
	 * @return int|bool The interval limit for the period, or false if not defined.
	 */
	private function get_period_interval_limit( $period ) {
		$max_intervals = [
			'year'  => 1,
			'month' => 12,
			'week'  => 52,
			'day'   => 365,
		];

		return ! empty( $max_intervals[ $period ] ) ? $max_intervals[ $period ] : false;
	}

	/**
	 * Deletes and archives a product WCPay Price IDs.
	 *
	 * @param WC_Product $product The WC Product object to delete and archive the a price IDs.
	 */
	private function delete_all_wcpay_price_ids( $product ) {
		// Delete and archive all price IDs for all environments.
		foreach ( [ 'test', 'live' ] as $environment ) {
			$test_mode         = 'test' === $environment;
			$price_id_meta_key = self::get_wcpay_price_id_option( $test_mode );

			if ( $product->meta_exists( $price_id_meta_key ) ) {
				try {
					$this->archive_price( $product->get_meta( $price_id_meta_key, true ), $test_mode );
				} catch ( API_Exception $e ) {
					Logger::log( 'There was a problem archiving the ' . $environment . 'product price ID in WC Pay: ' . $e->getMessage() );
				}

				// Now that the price has been archived, delete the record of it.
				$product->delete_meta_data( $price_id_meta_key );
			}
		}

		$product->delete_meta_data( self::PRICE_HASH_KEY );
		$product->save();
	}

	/**
	 * Validates that we have the data necessary to create a product in WCPay.
	 *
	 * @param  array $product_data Data used to create/update the product in WCPay.
	 * @throws Exception If the product data doesn't contain the 'name' argument as the 'name' property is a required field.
	 */
	private function validate_product_data( $product_data ) {
		if ( empty( $product_data['name'] ) ) {
			throw new Exception( 'The product "name" is required.' );
		}
	}

	/**
	 * Deprecated functions
	 */

	/**
	 * Unarchives a WC Pay Price object.
	 *
	 * @deprecated 3.3.0
	 *
	 * @param string    $wcpay_price_id The Price object's ID to unarchive.
	 * @param bool|null $test_mode      Is WC Pay in test/dev mode.
	 */
	public function unarchive_price( string $wcpay_price_id, $test_mode = null ) {
		wc_deprecated_function( __FUNCTION__, '3.3.0' );
		$data = [ 'active' => 'true' ];

		if ( null !== $test_mode ) {
			$data['test_mode'] = $test_mode;
		}

		$this->payments_api_client->update_price( $wcpay_price_id, $data );
	}

	/**
	 * Gets the WC Pay price hash associated with a WC product.
	 *
	 * @deprecated 3.3.0
	 *
	 * @param WC_Product $product The product to get the hash for.
	 * @return string             The product's price hash or an empty string.
	 */
	public static function get_wcpay_price_hash( WC_Product $product ) : string {
		wc_deprecated_function( __FUNCTION__, '3.3.0' );
		return $product->get_meta( self::PRICE_HASH_KEY, true );
	}

	/**
	 * Gets the WC Pay price ID associated with a WC product.
	 *
	 * @deprecated 3.3.0
	 *
	 * @param WC_Product $product   The product to get the WC Pay price ID for.
	 * @param bool|null  $test_mode Is WC Pay in test/dev mode.
	 *
	 * @return string The product's WC Pay price ID or an empty string.
	 */
	public function get_wcpay_price_id( WC_Product $product, $test_mode = null ) : string {
		wc_deprecated_function( __FUNCTION__, '3.3.0' );
		$price_id = $product->get_meta( self::get_wcpay_price_id_option( $test_mode ), true );

		// If the subscription product doesn't have a WC Pay price ID, create one now.
		if ( empty( $price_id ) && WC_Subscriptions_Product::is_subscription( $product ) ) {
			$is_current_environment = null === $test_mode || WC_Payments::mode()->is_test() === $test_mode;

			// Only create WCPay Price object if we're trying to getch a wcpay price ID in the current environment.
			if ( $is_current_environment ) {
				WC_Payments_Subscriptions::get_product_service()->create_product( $product );
				$price_id = $product->get_meta( self::get_wcpay_price_id_option(), true );
			}
		}

		return $price_id;
	}
}
