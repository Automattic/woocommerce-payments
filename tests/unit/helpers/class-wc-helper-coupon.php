<?php
/**
 * Coupon helpers.
 *
 * @package WooCommerce/Tests
 */

/**
 * Class WC_Helper_Coupon.
 *
 * This helper class should ONLY be used for unit tests!.
 */
class WC_Helper_Coupon {

	/**
	 * Create a dummy coupon.
	 *
	 * @param string $coupon_code
	 * @param array  $meta
	 *
	 * @return WC_Coupon
	 */
	public static function create_coupon( $coupon_code = 'dummycoupon', $meta = [] ) {
		// Insert post.
		$coupon_id = wp_insert_post(
			[
				'post_title'   => $coupon_code,
				'post_type'    => 'shop_coupon',
				'post_status'  => 'publish',
				'post_excerpt' => 'This is a dummy coupon',
			]
		);

		$meta = wp_parse_args(
			$meta,
			[
				'discount_type'              => 'fixed_cart',
				'coupon_amount'              => '1',
				'individual_use'             => 'no',
				'product_ids'                => '',
				'exclude_product_ids'        => '',
				'usage_limit'                => '',
				'usage_limit_per_user'       => '',
				'limit_usage_to_x_items'     => '',
				'expiry_date'                => '',
				'free_shipping'              => 'no',
				'exclude_sale_items'         => 'no',
				'product_categories'         => [],
				'exclude_product_categories' => [],
				'minimum_amount'             => '',
				'maximum_amount'             => '',
				'customer_email'             => [],
				'usage_count'                => '0',
			]
		);

		// Update meta.
		foreach ( $meta as $key => $value ) {
			update_post_meta( $coupon_id, $key, $value );
		}

		return new WC_Coupon( $coupon_code );
	}

}
