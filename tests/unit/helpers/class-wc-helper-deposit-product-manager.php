<?php
/**
 * WooCommerce Deposits helpers.
 *
 * @package WooCommerce\Payments\Tests
 */

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound

/**
 * Class WC_Deposits_Product_Manager.
 *
 * This helper class should ONLY be used for unit tests!.
 */
class WC_Deposits_Product_Manager {
	/**
	 * @param WC_Product_Simple|int $product
	 * @return mixed
	 */
	public static function get_deposit_type( $product ) {
		$product = self::get_product( $product );
		return $product->get_meta( '_wc_deposit_type' );
	}

	/**
	 * @param WC_Product_Simple|int $product
	 * @return mixed
	 */
	public static function get_deposit_selected_type( $product ) {
		$product = self::get_product( $product );
		return $product->get_meta( '_wc_deposit_selected_type' );
	}

	/**
	 * @param WC_Product_Simple|int $product
	 * @return bool
	 */
	public static function deposits_enabled( $product ) {
		$product = self::get_product( $product );
		$setting = $product->get_meta( '_wc_deposit_enabled' );
		return 'optional' === $setting || 'forced' === $setting;
	}

	/**
	 * @param WC_Product_Simple|int $product
	 * @return mixed
	 */
	public static function get_deposit_amount( $product, $plan_id = 0, $context = 'display', $product_price = null ) {
		$product    = self::get_product( $product );
		$type       = self::get_deposit_type( $product );
		$percentage = 'percent' === $type;
		$amount     = $product->get_meta( '_wc_deposit_amount' );

		if ( ! $amount ) {
			$amount = get_option( 'wc_deposits_default_amount' );
		}

		if ( ! $amount ) {
			return false;
		}

		if ( $percentage ) {
			$product_price = is_null( $product_price ) ? $product->get_price() : $product_price;
			$amount        = ( $product_price / 100 ) * $amount;
		}

		$price = $amount;
		return wc_format_decimal( $price );
	}

	/**
	 * @param $product
	 * @return mixed|null
	 */
	public static function get_product( $product ) {
		if ( ! is_object( $product ) ) {
			$product = apply_filters( 'test_deposit_get_product', wc_get_product( $product ) );
		}

		return $product;
	}
}

/**
 * Class WC_Deposits_Plans_Manager.
 */
class WC_Deposits_Plans_Manager {
	/**
	 * Get plan ids assigned to a product.
	 *
	 * @param  int $product_id Product ID.
	 * @return int[]
	 */
	public static function get_plan_ids_for_product( $product_id ) {
		$product = WC_Deposits_Product_Manager::get_product( $product_id );
		$map     = array_map( 'absint', array_filter( (array) $product->get_meta( '_wc_deposit_payment_plans' ) ) );
		if ( count( $map ) <= 0 ) {
			$map = self::get_default_plan_ids();
		}
		return $map;
	}

	/**
	 * Get the default plan IDs.
	 */
	public static function get_default_plan_ids() {
		return array_map( 'absint', array_filter( (array) get_option( 'wc_deposits_default_plans', [] ) ) );
	}
}
