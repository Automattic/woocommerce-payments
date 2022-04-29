<?php
/**
 * Class WC_Payments_Captured_Event_Note
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Utility class generating detailed captured note for successful payments.
 */
class WC_Payments_Captured_Event_Note {
	/**
	 * Captured event data.
	 *
	 * @var array
	 */
	private $captured_event;

	/**
	 * Constructor.
	 *
	 * @param  array $captured_event Captured event data.
	 *
	 * @throws Exception
	 */
	public function __construct( array $captured_event ) {
		$is_captured_event = isset( $captured_event['type'] ) && 'captured' === $captured_event['type'];
		if ( ! $is_captured_event ) {
			throw new Exception( 'Not a captured event' );
		}

		// TODO maybe do more check to ensure having to-be-used keys.
		$this->captured_event = $captured_event;
	}

	/**
	 * Generate FX string.
	 *
	 * @return ?string
	 */
	public function compose_fx_string() {
		if ( ! $this->is_fx_event() ) {
			return null;
		}

		$customer_currency = $this->captured_event['transaction_details']['customer_currency'];
		$customer_amount   = $this->captured_event['transaction_details']['customer_amount'];
		$store_currency    = $this->captured_event['transaction_details']['store_currency'];
		$store_amount      = $this->captured_event['transaction_details']['store_amount'];

		return $this->format_fx( $customer_currency, $customer_amount, $store_currency, $store_amount );
	}

	/**
	 * Check if it's a FX event.
	 *
	 * @return bool
	 */
	public function is_fx_event(): bool {
		$customer_currency = $this->captured_event['transaction_details']['customer_currency'] ?? null;
		$store_currency    = $this->captured_event['transaction_details']['store_currency'] ?? null;

		return ! (
			is_null( $customer_currency )
			|| is_null( $store_currency )
			|| $customer_currency === $store_currency
		);
	}

	/**
	 * Format FX string based on the two provided currencies.
	 *
	 * @param  string $from_currency 3-letter code for original currency.
	 * @param  int    $from_amount Amount (Stripe-type) for original currency.
	 * @param  string $to_currency 3-letter code for converted currency.
	 * @param  int    $to_amount Amount (Stripe-type) for converted currency.
	 *
	 * @return string Formatted FX string.
	 */
	public function format_fx(
		string $from_currency,
		int $from_amount,
		string $to_currency,
		int $to_amount
	): string {

		$exchange_rate = (float) ( 0 !== $from_amount
			? $to_amount / $from_amount
			: 0 );

		if ( WC_Payments_Utils::is_zero_decimal_currency( strtolower( $to_currency ) ) ) {
			$exchange_rate *= 100;
		}

		if ( WC_Payments_Utils::is_zero_decimal_currency( strtolower( $from_currency ) ) ) {
			$exchange_rate /= 100;
		}

		$to_display_amount = WC_Payments_Utils::interpret_stripe_amount( $to_amount, $to_currency );

		return sprintf(
			'%1$s → %2$s: %3$s',
			self::format_explicit_currency( 1, $from_currency, true ),
			self::format_exchange_rate( $exchange_rate, $to_currency ),
			self::format_explicit_currency( $to_display_amount, $to_currency, false )
		);

	}

	/**
	 * Format amount according to the given currency with the currency code in the right.
	 *
	 * @param  float  $amount          Amount.
	 * @param  string $currency       3-letter currency code.
	 * @param  bool   $skip_symbol      Optional. If true, trims off the short currency symbol. Default false.
	 * @param  array  $currency_format Optional. Addtional currency format for wc_price.
	 *
	 * @return string Formatted currency representation
	 */
	public static function format_explicit_currency( float $amount, string $currency, bool $skip_symbol = false, array $currency_format = [] ): string {
		$formatted_amount = wc_price(
			$amount,
			wp_parse_args( $currency_format, self::get_currency_format_for_wc_price( $currency ) )
		);

		$formatted_amount = html_entity_decode( wp_strip_all_tags( $formatted_amount ) );

		if ( $skip_symbol ) {
			$formatted_amount = preg_replace( '/[^0-9,\.]+/', '', $formatted_amount );
		}

		if ( false === strpos( $formatted_amount, strtoupper( $currency ) ) ) {
			return $formatted_amount . ' ' . strtoupper( $currency );
		}

		return $formatted_amount;
	}

	/**
	 * Format exchange rate.
	 *
	 * @param  float  $rate Exchange rate.
	 * @param  string $currency 3-letter currency code.
	 *
	 * @return string
	 */
	public static function format_exchange_rate( float $rate, string $currency ): string {
		$num_decimals = $rate > 1 ? 5 : 6;
		return self::format_explicit_currency( $rate, $currency, true, [ 'decimals' => $num_decimals ] );
	}

	/**
	 * Transforms the currency format returned from localization service into
	 * the format that can be used by wc_price
	 *
	 * @param string $currency the currency code.
	 * @return array The currency format.
	 */
	private static function get_currency_format_for_wc_price( string $currency ) : array {
		$currency_data                = WC_Payments::get_localization_service()->get_currency_format( $currency );
		$currency_format_for_wc_price = [];
		foreach ( $currency_data as $key => $format ) {
			switch ( $key ) {
				case 'thousand_sep':
					$currency_format_for_wc_price['thousand_separator'] = $format;
					break;
				case 'decimal_sep':
					$currency_format_for_wc_price['decimal_separator'] = $format;
					break;
				case 'num_decimals':
					$currency_format_for_wc_price['decimals'] = $format;
					break;
				case 'currency_pos':
					$currency_format_for_wc_price['price_format'] = self::get_woocommerce_price_format( $format );
					break;
			}
		}
		$currency_format_for_wc_price['currency'] = $currency;
		return $currency_format_for_wc_price;
	}

	/**
	 * Returns the currency format
	 *
	 * @param string $currency_pos currency symbol position.
	 * @return string The currency format.
	 */
	private static function get_woocommerce_price_format( string $currency_pos ): string {
		switch ( $currency_pos ) {
			case 'left':
				return '%1$s%2$s';
			case 'right':
				return '%2$s%1$s';
			case 'left_space':
				return '%1$s&nbsp;%2$s';
			case 'right_space':
				return '%2$s&nbsp;%1$s';
			default:
				return '%1$s%2$s';
		}
	}
}
