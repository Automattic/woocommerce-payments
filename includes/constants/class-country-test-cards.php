<?php
/**
 * Class Country_Test_Cards
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


/**
 * Class handling country-specific data for WooPayments
 */
class Country_Test_Cards extends Base_Constant {
	/**
	 * Map of country codes to their test card numbers
	 * Source: https://docs.stripe.com/testing?testing-method=card-numbers#international-cards
	 *
	 * @var array
	 */
	private static $country_test_cards = [
		'US' => '4242424242424242',
		'AR' => '4000000320000021',
		'BR' => '4000000760000002',
		'CA' => '4000001240000000',
		'CL' => '4000001520000001',
		'CO' => '4000001700000003',
		'CR' => '4000001880000005',
		'EC' => '4000002180000000',
		'MX' => '4000004840008001',
		'PA' => '4000005910000000',
		'PY' => '4000006000000066',
		'PE' => '4000006040000068',
		'UY' => '4000008580000003',
		'AE' => '4000007840000001',
		'AT' => '4000000400000008',
		'BE' => '4000000560000004',
		'BG' => '4000001000000000',
		'BY' => '4000001120000005',
		'HR' => '4000001910000009',
		'CY' => '4000001960000008',
		'CZ' => '4000002030000002',
		'DK' => '4000002080000001',
		'EE' => '4000002330000009',
		'FI' => '4000002460000001',
		'FR' => '4000002500000003',
		'DE' => '4000002760000016',
		'GI' => '4000002920000005',
		'GR' => '4000003000000030',
		'HU' => '4000003480000005',
		'IE' => '4000003720000005',
		'IT' => '4000003800000008',
		'LV' => '4000004280000005',
		'LI' => '4000004380000004',
		'LT' => '4000004400000000',
		'LU' => '4000004420000006',
		'MT' => '4000004700000007',
		'NL' => '4000005280000002',
		'NO' => '4000005780000007',
		'PL' => '4000006160000005',
		'PT' => '4000006200000007',
		'RO' => '4000006420000001',
		'SA' => '4000006820000007',
		'SI' => '4000007050000006',
		'SK' => '4000007030000001',
		'ES' => '4000007240000007',
		'SE' => '4000007520000008',
		'CH' => '4000007560000009',
		'GB' => '4000008260000000',
		'AU' => '4000000360000006',
		'CN' => '4000001560000002',
		'HK' => '4000003440000004',
		'IN' => '4000003560000008',
		'JP' => '4000003920000003',
		'MY' => '4000004580000002',
		'NZ' => '4000005540000008',
		'SG' => '4000007020000003',
		'TW' => '4000001580000008',
		'TH' => '4000007640000003',
	];

	/**
	 * Get test card number for a specific country.
	 *
	 * @param string $country_code Two-letter country code.
	 * @return string Test card number
	 */
	public static function get_test_card_for_country( string $country_code ) {
		return self::$country_test_cards[ $country_code ] ?? self::$country_test_cards['US'];
	}
}
