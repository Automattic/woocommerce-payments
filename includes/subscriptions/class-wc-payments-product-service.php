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
	/**
	 * The product meta key used to store the product data we last sent to WC Pay as a hash. Used to compare current WC product data with WC Pay data.
	 *
	 * @const string
	 */
	const PRODUCT_HASH_KEY = '_wcpay_product_hash';

	/**
	 * The product meta key used to store the product's ID in WC Pay.
	 *
	 * @const string
	 */
	const PRODUCT_ID_KEY = '_wcpay_product_id';

	/**
	 * The product price meta key used to store the price data we last sent to WC Pay as a hash. Used to compare current WC product price data with WC Pay data.
	 *
	 * @const string
	 */
	const PRICE_HASH_KEY = '_wcpay_product_price_hash';

	/**
	 * The product meta key used to store the product's WC Pay Price object ID.
	 *
	 * @const string
	 */
	const PRICE_ID_KEY = '_wcpay_product_price_id';

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
	 * @param WC_Product $product The product to get the WC Pay ID for.
	 * @return string             The WC Pay product ID or an empty string.
	 */
	public function get_wcpay_product_id( WC_Product $product ) : string {

		// If the subscription product doesn't have a WC Pay product ID, create one.
		if ( ! self::has_wcpay_product_id( $product ) && WC_Subscriptions_Product::is_subscription( $product ) ) {
			WC_Payments_Subscriptions::get_product_service()->create_product( $product );
		}

		return $product->get_meta( self::PRODUCT_ID_KEY, true );
	}

	/**
	 * Gets the WC Pay price hash associated with a WC product.
	 *
	 * @param WC_Product $product The product to get the hash for.
	 * @return string             The product's price hash or an empty string.
	 */
	public static function get_wcpay_price_hash( WC_Product $product ) : string {
		return $product->get_meta( self::PRICE_HASH_KEY, true );
	}

	/**
	 * Gets the WC Pay price ID associated with a WC product.
	 *
	 * @param WC_Product $product The product to get the WC Pay price ID for.
	 * @return string             The product's WC Pay price ID or an empty string.
	 */
	public function get_wcpay_price_id( WC_Product $product ) : string {
		$price_id = $product->get_meta( self::PRICE_ID_KEY, true );

		// If the subscription product doesn't have a WC Pay price ID, create one now.
		if ( empty( $price_id ) && WC_Subscriptions_Product::is_subscription( $product ) ) {
			WC_Payments_Subscriptions::get_product_service()->create_product( $product );
			$price_id = $product->get_meta( self::PRICE_ID_KEY, true );
		}

		return $price_id;
	}

	/**
	 * Gets the WC Pay product ID associated with a WC product.
	 *
	 * @param string $type The item type to create a product for.
	 * return string       The item's WCPay product id.
	 */
	public function get_stripe_product_id_for_item( string $type ) : string {
		if ( ! get_option( self::PRODUCT_ID_KEY . '_' . $type ) ) {
			$this->create_product_for_item_type( $type );
		}

		return get_option( self::PRODUCT_ID_KEY . '_' . $type );
	}

	/**
	 * Check if the WC product has a WC Pay product ID.
	 *
	 * @param WC_Product $product The product to get the WC Pay ID for.
	 * @return string             The WC Pay product ID or an empty string.
	 */
	public static function has_wcpay_product_id( WC_Product $product ) : string {
		return (bool) $product->get_meta( self::PRODUCT_ID_KEY, true );
	}

	/**
	 * Prevents duplicate WC Pay product IDs and hashes when duplicating a subscription product.
	 *
	 * @param array $meta_keys The keys to exclude from the duplicate.
	 * @return array Keys to exclude.
	 */
	public static function exclude_meta_wcpay_product( $meta_keys ) {
		return array_merge( $meta_keys, [ self::PRODUCT_ID_KEY, self::PRODUCT_HASH_KEY, self::PRICE_ID_KEY, self::PRICE_HASH_KEY ] );
	}

	/**
	 * Schedules a subscription product to be created or updated in WC Pay on shutdown.
	 *
	 * @since x.x.x
	 *
	 * @param int        $product_id The ID of the product to handle.
	 * @param WC_Product $product    The product object to handle. Only subscription products will be created or updated in WC Pay.
	 */
	public function maybe_schedule_product_create_or_update( int $product_id, WC_Product $product ) {

		// Skip products which have already been scheduled or aren't subscriptions.
		if ( isset( $this->products_to_update[ $product_id ] ) || ! WC_Subscriptions_Product::is_subscription( $product ) ) {
			return;
		}

		foreach ( $this->get_products_to_update( $product ) as $product_to_update ) {
			// Skip products already scheduled.
			if ( isset( $this->products_to_update[ $product_to_update->get_id() ] ) ) {
				continue;
			}

			if ( ! self::has_wcpay_product_id( $product_to_update ) || $this->product_needs_update( $product_to_update ) || $this->price_needs_update( $product_to_update ) ) {
				$this->products_to_update[ $product_to_update->get_id() ] = $product_to_update->get_id();
			}
		}
	}

	/**
	 * Creates and updates all products which have been scheduled for an update.
	 *
	 * Hooked onto shutdown so all products which have been changed in the current request can be updated once.
	 *
	 * @since x.x.x
	 */
	public function create_or_update_products() {
		foreach ( $this->products_to_update as $product_id ) {
			$product = wc_get_product( $product_id );

			if ( ! $product ) {
				continue;
			}

			// If this product already has a WC Pay ID update it, otherwise create a new one.
			if ( self::has_wcpay_product_id( $product ) ) {
				$this->update_product( $product );
			} else {
				$this->create_product( $product );
			}
		}
	}

	/**
	 * Creates a product in WC Pay.
	 *
	 * @param WC_Product $product The product to create.
	 */
	public function create_product( WC_Product $product ) {
		try {
			$product_data  = array_merge( $this->get_product_data( $product ), $this->get_price_data( $product ) );
			$wcpay_product = $this->payments_api_client->create_product( $product_data );

			$this->remove_product_update_listeners();
			$this->set_wcpay_product_hash( $product, $this->get_product_hash( $product ) );
			$this->set_wcpay_product_id( $product, $wcpay_product['wcpay_product_id'] );
			$this->set_wcpay_price_hash( $product, $this->get_price_hash( $product ) );
			$this->set_wcpay_price_id( $product, $wcpay_product['wcpay_price_id'] );
			$this->add_product_update_listeners();
		} catch ( API_Exception $e ) {
			Logger::log( 'There was a problem creating the product in WC Pay: ' . $e->getMessage() );
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

			update_option( self::PRODUCT_ID_KEY . '_' . $type, $wcpay_product['wcpay_product_id'] );
		} catch ( API_Exception $e ) {
			Logger::log( 'There was a problem creating the product on WCPay Server: ' . $e->getMessage() );
		}
	}

	/**
	 * Updates a product in WC Pay.
	 *
	 * @param WC_Product $product The product to update.
	 */
	public function update_product( WC_Product $product ) {

		// If the product doesn't have a WC Pay ID yet, create it instead.
		if ( ! self::has_wcpay_product_id( $product ) ) {
			$this->create_product( $product );
			return;
		}

		$wcpay_product_id = $this->get_wcpay_product_id( $product );
		$data             = [];

		if ( $this->product_needs_update( $product ) ) {
			$data = array_merge( $data, $this->get_product_data( $product ) );
		}

		if ( $this->price_needs_update( $product ) ) {
			$data = array_merge( $data, $this->get_price_data( $product ) );
		}

		if ( ! empty( $data ) ) {
			try {
				$wcpay_product = $this->payments_api_client->update_product( $wcpay_product_id, $data );

				$this->remove_product_update_listeners();

				if ( isset( $wcpay_product['wcpay_product_id'] ) ) {
					$this->set_wcpay_product_hash( $product, $this->get_product_hash( $product ) );
				}

				if ( isset( $wcpay_product['wcpay_price_id'] ) ) {
					$old_wcpay_price_id = $this->get_wcpay_price_id( $product );

					$this->set_wcpay_price_hash( $product, $this->get_price_hash( $product ) );
					$this->set_wcpay_price_id( $product, $wcpay_product['wcpay_price_id'] );
					$this->archive_price( $old_wcpay_price_id );
				}

				$this->add_product_update_listeners();
			} catch ( API_Exception $e ) {
				Logger::log( 'There was a problem updating the product in WC Pay: ' . $e->getMessage() );
			}
		}
	}

	/**
	 * Archives a subscription product in WC Pay.
	 *
	 * @since x.x.x
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
	 * @since x.x.x
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
	 * Archives a product in WC Pay.
	 *
	 * @param WC_Product $product The product to archive.
	 */
	public function archive_product( WC_Product $product ) {

		if ( ! self::has_wcpay_product_id( $product ) ) {
			return;
		}

		try {
			$this->archive_price( $this->get_wcpay_price_id( $product ) );
			$this->payments_api_client->update_product( $this->get_wcpay_product_id( $product ), [ 'active' => 'false' ] );
		} catch ( API_Exception $e ) {
			Logger::log( 'There was a problem archiving the product in WC Pay: ' . $e->getMessage() );
		}
	}

	/**
	 * Unarchives a product in WC Pay.
	 *
	 * @param WC_Product $product The product unarchive.
	 */
	public function unarchive_product( WC_Product $product ) {

		if ( ! self::has_wcpay_product_id( $product ) ) {
			return;
		}

		try {
			$this->unarchive_price( $this->get_wcpay_price_id( $product ) );
			$this->payments_api_client->update_product( $this->get_wcpay_product_id( $product ), [ 'active' => 'true' ] );
		} catch ( API_Exception $e ) {
			Logger::log( 'There was a problem unarchiving the product in WC Pay: ' . $e->getMessage() );
		}
	}

	/**
	 * Archives a WC Pay price object.
	 *
	 * @param string $wcpay_product_id The price object's ID to archive.
	 */
	public function archive_price( string $wcpay_product_id ) {
		$this->payments_api_client->update_price( $wcpay_product_id, [ 'active' => 'false' ] );
	}

	/**
	 * Unarchives a WC Pay Price object.
	 *
	 * @param string $wcpay_product_id The Price object's ID to unarchive.
	 */
	public function unarchive_price( string $wcpay_product_id ) {
		$this->payments_api_client->update_price( $wcpay_product_id, [ 'active' => 'true' ] );
	}

	/**
	 * Attaches the callbacks used to update product changes in WC Pay.
	 */
	private function add_product_update_listeners() {
		add_action( 'woocommerce_update_product_variation', [ $this, 'maybe_schedule_product_create_or_update' ], 10, 2 );
		add_action( 'woocommerce_update_product', [ $this, 'maybe_schedule_product_create_or_update' ], 10, 2 );
	}

	/**
	 * Removes the callbacks used to update product changes in WC Pay.
	 */
	private function remove_product_update_listeners() {
		remove_action( 'woocommerce_update_product_variation', [ $this, 'maybe_schedule_product_create_or_update' ], 10 );
		remove_action( 'woocommerce_update_product', [ $this, 'maybe_schedule_product_create_or_update' ], 10 );
	}

	/**
	 * Gets product data from a subscription needed to create a WCPay subscription.
	 *
	 * @param WC_Subscription $subscription The WC subscription to fetch product data from.
	 *
	 * @return array|null WCPay Product data or null on error.
	 */
	public function get_product_data_for_subscription( WC_Subscription $subscription ) {
		$product_data = [];

		foreach ( $subscription->get_items() as $item ) {
			$product = $item->get_product();

			if ( ! WC_Subscriptions_Product::is_subscription( $product ) ) {
				continue;
			}

			$product_data[] = [
				'price'     => $this->get_wcpay_price_id( $product ),
				'quantity'  => $item->get_quantity(),
				'tax_rates' => $this->get_tax_rates_for_item( $item, $subscription ),
			];
		}

		return $product_data;
	}

	/**
	 * Prepare tax rates for a subscription item.
	 *
	 * @param WC_Order_Item   $item         Subscription order item.
	 * @param WC_Subscription $subscription A Subscription to get tax rate information from.
	 *
	 * @return array
	 */
	public function get_tax_rates_for_item( WC_Order_Item $item, WC_Subscription $subscription ) {
		$tax_rates = [];

		if ( ! wc_tax_enabled() || ! $item->get_taxes() ) {
			return $tax_rates;
		}

		$tax_rate_ids = array_keys( $item->get_taxes()['total'] );

		if ( ! $tax_rate_ids ) {
			return $tax_rates;
		}

		$tax_inclusive = wc_prices_include_tax();

		foreach ( $subscription->get_taxes() as $tax ) {
			if ( in_array( $tax->get_rate_id(), $tax_rate_ids, true ) ) {
				$tax_rates[] = [
					'display_name' => $tax->get_name(),
					'inclusive'    => $tax_inclusive,
					'percentage'   => $tax->get_rate_percent(),
				];
			}
		}

		return $tax_rates;
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
	 * Gets price data relevant to WC Pay from a WC product.
	 *
	 * @param WC_Product $product The product to get data from.
	 * @return array
	 */
	private function get_price_data( WC_Product $product ) : array {
		return [
			'currency'       => get_woocommerce_currency(),
			'interval'       => WC_Subscriptions_Product::get_period( $product ),
			'interval_count' => WC_Subscriptions_Product::get_interval( $product ),
			'unit_amount'    => $product->get_price() * 100,
		];
	}

	/**
	 * Gets the products to update from a given product.
	 *
	 * If applicable, returns the product's variations otherwise returns the product by itself.
	 *
	 * @param WC_Product $product The product.
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
	 * @return string             The product's price hash.
	 */
	private function get_product_hash( WC_Product $product ) : string {
		return md5( implode( $this->get_product_data( $product ) ) );
	}

	/**
	 * Gets a hash of the product's price, period, and inverval.
	 * Used to compare WC changes with WC Pay data.
	 *
	 * @param WC_Product $product The product to generate the hash for.
	 * @return string             The product's price hash.
	 */
	private function get_price_hash( WC_Product $product ) : string {
		return md5( implode( $this->get_price_data( $product ) ) );
	}

	/**
	 * Checks if a prouduct needs to be updated in WC Pay.
	 *
	 * @param WC_Product $product The product to check updates for.
	 *
	 * @return bool Whether the product needs to be update in WC Pay.
	 */
	private function product_needs_update( WC_Product $product ) : bool {
		return $this->get_product_hash( $product ) !== $this->get_wcpay_product_hash( $product );
	}

	/**
	 * Checks if a prouduct price needs to be updated in WC Pay.
	 *
	 * @param WC_Product $product The product to check updates for.
	 *
	 * @return bool Whether the product price needs to be update in WC Pay.
	 */
	private function price_needs_update( WC_Product $product ) : bool {
		return $this->get_price_hash( $product ) !== $this->get_wcpay_price_hash( $product );
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
		$product->update_meta_data( self::PRODUCT_ID_KEY, $value );
		$product->save();
	}

	/**
	 * Sets a WC Pay price hash on a WC product.
	 *
	 * @param WC_Product $product The product to set the WC Pay price hash for.
	 * @param string     $value   The WC Pay product hash.
	 */
	private function set_wcpay_price_hash( WC_Product $product, string $value ) {
		$product->update_meta_data( self::PRICE_HASH_KEY, $value );
		$product->save();
	}

	/**
	 * Set a WC Pay price ID on a WC product.
	 *
	 * @param WC_Product $product The product to set the WC Pay price ID for.
	 * @param string     $value   The WC Pay price ID.
	 */
	private function set_wcpay_price_id( WC_Product $product, string $value ) {
		$product->update_meta_data( self::PRICE_ID_KEY, $value );
		$product->save();
	}
}
