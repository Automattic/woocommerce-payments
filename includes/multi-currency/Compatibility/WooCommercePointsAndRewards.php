<?php
/**
 * Class WooCommercePreOrders
 *
 * @package WCPay\MultiCurrency\Compatibility
 */

namespace WCPay\MultiCurrency\Compatibility;

/**
 * Class that controls Multi Currency Compatibility with WooCommerce Points & Rewards Plugin.
 */
class WooCommercePointsAndRewards extends BaseCompatibility {

	/**
	 * Init the class.
	 *
	 * @return  void
	 */
	protected function init() {
		// Add needed filters if Points & Rewards is active and it's not an admin request.
		if ( is_admin() || ! class_exists( 'WC_Points_Rewards' ) ) {
			return;
		}

		$default_currency  = $this->multi_currency->get_default_currency();
		$selected_currency = $this->multi_currency->get_selected_currency();

		// Skip it if selected currency and default store currency match.
		if ( $default_currency->code === $selected_currency->code ) {
			return;
		}

		add_filter( 'option_wc_points_rewards_earn_points_ratio', [ $this, 'convert_points_ratio' ], 50 );
		add_filter( 'option_wc_points_rewards_redeem_points_ratio', [ $this, 'convert_points_ratio' ], 50 );
	}

	/**
	 * Converts points ratio applying selected currency rate to the monetary value.
	 *
	 * @param string $ratio Store currency points ratio.
	 * @return string Converted points ratio.
	 */
	public function convert_points_ratio( string $ratio = '' ): string {
		// Skip conversion on discount to avoid doing it twice.
		if ( $this->utils->is_call_in_backtrace( [ 'WC_Points_Rewards_Discount->get_discount_data' ] ) ) {
			return $ratio;
		}

		$ratio  = explode( ':', $ratio );
		$points = (float) ( $ratio[0] ?? 0 );
		$value  = (float) ( $ratio[1] ?? 0 );

		$selected_currency = $this->multi_currency->get_selected_currency();
		$value             = (float) $value * $selected_currency->get_rate();

		return "$points:$value";
	}
}
