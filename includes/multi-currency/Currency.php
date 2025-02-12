<?php
/**
 * Class Currency
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\MultiCurrency;

use WCPay\MultiCurrency\Interfaces\MultiCurrencyLocalizationInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Multi-Currency Currency object.
 */
class Currency implements \JsonSerializable {

	/**
	 * Three letter currency code.
	 *
	 * @var string
	 */
	public $code;

	/**
	 * Currency conversion rate.
	 *
	 * @var float
	 */
	public $rate;

	/**
	 * Currency charm rate after conversion and rounding.
	 *
	 * @var float|null
	 */
	private $charm;

	/**
	 * Is currency default for store?
	 *
	 * @var bool
	 */
	private $is_default = false;

	/**
	 * Currency rounding rate after conversion.
	 *
	 * @var string|null
	 */
	private $rounding;

	/**
	 * Is currency zero decimal?
	 *
	 * @var bool
	 */
	private $is_zero_decimal = false;

	/**
	 * A timestamp representing the time this currency was last fetched successfully from the server.
	 *
	 * @var int|null
	 */
	private $last_updated;

	/**
	 * Instance of MultiCurrencyLocalizationInterface.
	 *
	 * @var MultiCurrencyLocalizationInterface
	 */
	private $localization_service;

	/**
	 * Constructor.
	 *
	 * @param MultiCurrencyLocalizationInterface $localization_service Localization service instance.
	 * @param string                             $code Three letter currency code.
	 * @param float                              $rate The conversion rate.
	 * @param int|null                           $last_updated The time this currency was last updated.
	 */
	public function __construct( MultiCurrencyLocalizationInterface $localization_service, $code = '', float $rate = 1.0, $last_updated = null ) {
		$this->localization_service = $localization_service;
		$this->code                 = $code;
		$this->rate                 = $rate;

		if ( get_woocommerce_currency() === $code ) {
			$this->is_default = true;
		}

		// Set zero-decimal style based on WC locale information.
		$this->is_zero_decimal = 0 === $this->localization_service->get_currency_format( $code )['num_decimals'];

		if ( ! is_null( $last_updated ) ) {
			$this->last_updated = $last_updated;
		}
	}

	/**
	 * Retrieves the currency's translated name from WooCommerce core.
	 *
	 * @param string $code The currency code.
	 */
	public function get_currency_name_from_code( $code ): string {
		$wc_currencies = get_woocommerce_currencies();
		return $wc_currencies[ $code ];
	}

	/**
	 * Retrieves the currency's code.
	 *
	 * @return string Three letter currency code.
	 */
	public function get_code(): string {
		return $this->code;
	}

	/**
	 * Retrieves the currency's charm rate.
	 *
	 * @return float Charm rate.
	 */
	public function get_charm(): float {
		return is_null( $this->charm ) ? 0.00 : $this->charm;
	}

	/**
	 * Retrieves the currency's flag.
	 *
	 * @return string Currency flag.
	 */
	public function get_flag(): string {
		// Maybe add param img/emoji to return which you want?
		return CountryFlags::get_by_currency( $this->code );
	}

	/**
	 * Retrieves the currency code lowercased.
	 *
	 * @return string Currency code lowercased.
	 */
	public function get_id(): string {
		return strtolower( $this->code );
	}

	/**
	 * Retrieves if the currency is default for the store.
	 *
	 * @return bool
	 */
	public function get_is_default(): bool {
		return $this->is_default;
	}

	/**
	 * Retrieves the currency's name from WooCommerce core.
	 *
	 * @return string Currency name.
	 */
	public function get_name(): string {
		$wc_currencies = get_woocommerce_currencies();
		return $wc_currencies[ $this->code ];
	}

	/**
	 * Retrieves the currency's conversion rate.
	 *
	 * @return float The conversion rate.
	 */
	public function get_rate(): float {
		return $this->rate;
	}

	/**
	 * Retrieves the currency's rounding rate.
	 *
	 * @return string Rounding rate.
	 */
	public function get_rounding(): string {
		return (string) ( $this->rounding ?? '0' );
	}

	/**
	 * Retrieves the currency's symbol from WooCommerce core.
	 *
	 * @return string Currency symbol.
	 */
	public function get_symbol(): string {
		return get_woocommerce_currency_symbol( $this->code );
	}

	/**
	 * Retrieves the currency's symbol position from Localization Service
	 *
	 * @return  string  Currency position (left/right).
	 */
	public function get_symbol_position(): string {
		return $this->localization_service->get_currency_format( $this->code )['currency_pos'];
	}

	/**
	 * Retrieves if the currency is zero decimal.
	 *
	 * @return bool
	 */
	public function get_is_zero_decimal(): bool {
		return $this->is_zero_decimal;
	}

	/**
	 * Get the timestamp reprenting when the currency was last updated.
	 *
	 * @return int|null A timestamp representing when the currency was last updated.
	 */
	public function get_last_updated() {
		return $this->last_updated;
	}

	/**
	 * Sets the currency's charm rate.
	 *
	 * @param float $charm Charm rate.
	 */
	public function set_charm( $charm ) {
		$this->charm = $charm;
	}

	/**
	 * Sets the currency's conversion rate.
	 *
	 * @param float $rate Conversion rate.
	 */
	public function set_rate( $rate ) {
		$this->rate = $rate;
	}

	/**
	 * Sets the currency's rounding rate.
	 *
	 * @param string $rounding Rounding rate.
	 * @return void
	 */
	public function set_rounding( $rounding ) {
		$this->rounding = $rounding;
	}

	/**
	 * Set the currency's last updated timestamp.
	 *
	 * @param int $last_updated A timestamp representing when the currency was last updated.
	 *
	 * @return void
	 */
	public function set_last_updated( int $last_updated ) {
		$this->last_updated = $last_updated;
	}

	/**
	 * Specify the data that should be serialized to JSON.
	 *
	 * @return array Serialized Currency object.
	 */
	public function jsonSerialize(): array {
		return [
			'code'            => $this->code,
			'rate'            => $this->get_rate(),
			'name'            => html_entity_decode( $this->get_name() ),
			'id'              => $this->get_id(),
			'is_default'      => $this->get_is_default(),
			'flag'            => $this->get_flag(),
			'symbol'          => html_entity_decode( $this->get_symbol() ),
			'symbol_position' => $this->get_symbol_position(),
			'is_zero_decimal' => $this->get_is_zero_decimal(),
			'last_updated'    => $this->get_last_updated(),
		];
	}
}
