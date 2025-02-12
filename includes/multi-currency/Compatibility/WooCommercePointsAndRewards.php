<?php
/**
 * Class WooCommercePointsAndRewards
 *
 * @package WCPay\MultiCurrency\Compatibility
 */

namespace WCPay\MultiCurrency\Compatibility;

use WCPay\MultiCurrency\Currency;

/**
 * Class that controls Multi Currency Compatibility with WooCommerce Points & Rewards Plugin.
 */
class WooCommercePointsAndRewards extends BaseCompatibility {

	/**
	 * Default Currency Code.
	 *
	 * @var string
	 */
	private $default_currency_code;

	/**
	 * Selected Currency Code.
	 *
	 * @var Currency
	 */
	private $selected_currency;

	/**
	 * Init the class.
	 *
	 * @return  void
	 */
	public function init() {
		// Add needed filters if Points & Rewards is active and it's not an admin request.
		if ( is_admin() || ! class_exists( 'WC_Points_Rewards' ) ) {
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
		// Skip conversion if selected and default currencies are the same.
		if ( $this->selected_and_default_currency_match() ) {
			return $ratio;
		}

		// Skip conversion on discount to avoid doing it twice.
		if ( $this->utils->is_call_in_backtrace( [ 'WC_Points_Rewards_Discount->get_discount_data' ] ) ) {
			return $ratio;
		}

		$ratio  = explode( ':', $ratio );
		$points = (float) ( $ratio[0] ?? 0 );
		$value  = (float) ( $ratio[1] ?? 0 );

		$rate  = $this->selected_currency->get_rate();
		$value = $value * $rate;

		return "$points:$value";
	}

	/**
	 * Whether the selected and default currency are the same.
	 *
	 * @return boolean
	 */
	private function selected_and_default_currency_match(): bool {
		if ( empty( $this->default_currency_code ) ) {
			$this->default_currency_code = $this->multi_currency->get_default_currency()->get_code();
		}
		if ( empty( $this->selected_currency ) ) {
			$this->selected_currency = $this->multi_currency->get_selected_currency();
		}

		return $this->default_currency_code === $this->selected_currency->get_code();
	}
}
