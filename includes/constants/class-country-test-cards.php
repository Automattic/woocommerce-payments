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
 * Class handling country-specific test card numbers for WooPayments
 */
class Country_Test_Cards extends Base_Constant {
	/**
	 * Map of country codes to their test card numbers
	 * Source: https://docs.stripe.com/testing?testing-method=card-numbers#international-cards
	 *
	 * @var array
	 */
	private static $country_test_cards = [
		Country_Code::UNITED_STATES        => '4242 4242 4242 4242',
		Country_Code::ARGENTINA            => '4000 0003 2000 0021',
		Country_Code::BRAZIL               => '4000 0007 6000 0002',
		Country_Code::CANADA               => '4000 0012 4000 0000',
		Country_Code::CHILE                => '4000 0015 2000 0001',
		Country_Code::COLOMBIA             => '4000 0017 0000 0003',
		Country_Code::COSTA_RICA           => '4000 0018 8000 0005',
		Country_Code::ECUADOR              => '4000 0021 8000 0000',
		Country_Code::MEXICO               => '4000 0048 4000 8001',
		Country_Code::PANAMA               => '4000 0059 1000 0000',
		Country_Code::PARAGUAY             => '4000 0060 0000 0066',
		Country_Code::PERU                 => '4000 0060 4000 0068',
		Country_Code::URUGUAY              => '4000 0085 8000 0003',
		Country_Code::UNITED_ARAB_EMIRATES => '4000 0078 4000 0001',
		Country_Code::AUSTRIA              => '4000 0004 0000 0008',
		Country_Code::BELGIUM              => '4000 0005 6000 0004',
		Country_Code::BULGARIA             => '4000 0010 0000 0000',
		Country_Code::BELARUS              => '4000 0011 2000 0005',
		Country_Code::CROATIA              => '4000 0019 1000 0009',
		Country_Code::CYPRUS               => '4000 0019 6000 0008',
		Country_Code::CZECHIA              => '4000 0020 3000 0002',
		Country_Code::DENMARK              => '4000 0020 8000 0001',
		Country_Code::ESTONIA              => '4000 0023 3000 0009',
		Country_Code::FINLAND              => '4000 0024 6000 0001',
		Country_Code::FRANCE               => '4000 0025 0000 0003',
		Country_Code::GERMANY              => '4000 0027 6000 0016',
		Country_Code::GIBRALTAR            => '4000 0029 2000 0005',
		Country_Code::GREECE               => '4000 0030 0000 0030',
		Country_Code::HUNGARY              => '4000 0034 8000 0005',
		Country_Code::IRELAND              => '4000 0037 2000 0005',
		Country_Code::ITALY                => '4000 0038 0000 0008',
		Country_Code::LATVIA               => '4000 0042 8000 0005',
		Country_Code::LIECHTENSTEIN        => '4000 0043 8000 0004',
		Country_Code::LITHUANIA            => '4000 0044 0000 0000',
		Country_Code::LUXEMBOURG           => '4000 0044 2000 0006',
		Country_Code::MALTA                => '4000 0047 0000 0007',
		Country_Code::NETHERLANDS          => '4000 0052 8000 0002',
		Country_Code::NORWAY               => '4000 0057 8000 0007',
		Country_Code::POLAND               => '4000 0061 6000 0005',
		Country_Code::PORTUGAL             => '4000 0062 0000 0007',
		Country_Code::ROMANIA              => '4000 0064 2000 0001',
		Country_Code::SAUDI_ARABIA         => '4000 0068 2000 0007',
		Country_Code::SLOVENIA             => '4000 0070 5000 0006',
		Country_Code::SLOVAKIA             => '4000 0070 3000 0001',
		Country_Code::SPAIN                => '4000 0072 4000 0007',
		Country_Code::SWEDEN               => '4000 0075 2000 0008',
		Country_Code::SWITZERLAND          => '4000 0075 6000 0009',
		Country_Code::UNITED_KINGDOM       => '4000 0082 6000 0000',
		Country_Code::AUSTRALIA            => '4000 0003 6000 0006',
		Country_Code::CHINA                => '4000 0015 6000 0002',
		Country_Code::HONG_KONG            => '4000 0034 4000 0004',
		Country_Code::INDIA                => '4000 0035 6000 0008',
		Country_Code::JAPAN                => '4000 0039 2000 0003',
		Country_Code::MALAYSIA             => '4000 0045 8000 0002',
		Country_Code::NEW_ZEALAND          => '4000 0055 4000 0008',
		Country_Code::SINGAPORE            => '4000 0070 2000 0003',
		Country_Code::TAIWAN               => '4000 0015 8000 0008',
		Country_Code::THAILAND             => '4000 0076 4000 0003',
	];

	/**
	 * Get test card number for a specific country.
	 *
	 * @param string $country_code Two-letter country code.
	 * @return string Test card number
	 */
	public static function get_test_card_for_country( string $country_code ) {
		return self::$country_test_cards[ $country_code ] ?? self::$country_test_cards[ Country_Code::UNITED_STATES ];
	}
}
