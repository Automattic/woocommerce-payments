<?php
/**
 * Class CountryFlags
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\MultiCurrency;

use WCPay\Constants\Country_Codes;

defined( 'ABSPATH' ) || exit;

/**
 * Class that bring flags per country/currency.
 */
class CountryFlags {

	const EMOJI_COUNTRIES_FLAGS = [
		Country_Codes::ANDORRA                          => '🇦🇩',
		'AE'                                            => '🇦🇪',
		Country_Codes::AFGHANISTAN                      => '🇦🇫',
		Country_Codes::ANTIGUA_AND_BARBUDA              => '🇦🇬',
		'AI'                                            => '🇦🇮',
		Country_Codes::ALBANIA                          => '🇦🇱',
		Country_Codes::ARMENIA                          => '🇦🇲',
		Country_Codes::ANGOLA                           => '🇦🇴',
		'AQ'                                            => '🇦🇶',
		Country_Codes::ARGENTINA                        => '🇦🇷',
		'AS'                                            => '🇦🇸',
		Country_Codes::AUSTRIA                          => '🇦🇹',
		Country_Codes::AUSTRALIA                        => '🇦🇺',
		'AW'                                            => '🇦🇼',
		'AX'                                            => '🇦🇽',
		Country_Codes::AZERBAIJAN                       => '🇦🇿',
		Country_Codes::BOSNIA_AND_HERZEGOVINA           => '🇧🇦',
		Country_Codes::BARBADOS                         => '🇧🇧',
		Country_Codes::BANGLADESH                       => '🇧🇩',
		Country_Codes::BELGIUM                          => '🇧🇪',
		Country_Codes::BURKINA_FASO                     => '🇧🇫',
		Country_Codes::BULGARIA                         => '🇧🇬',
		Country_Codes::BAHRAIN                          => '🇧🇭',
		Country_Codes::BURUNDI                          => '🇧🇮',
		Country_Codes::BENIN                            => '🇧🇯',
		'BL'                                            => '🇧🇱',
		'BM'                                            => '🇧🇲',
		Country_Codes::BRUNEI                           => '🇧🇳',
		Country_Codes::BOLIVIA                          => '🇧🇴',
		'BQ'                                            => '🇧🇶',
		Country_Codes::BRAZIL                           => '🇧🇷',
		Country_Codes::BAHAMAS                          => '🇧🇸',
		Country_Codes::BHUTAN                           => '🇧🇹',
		'BV'                                            => '🇧🇻',
		Country_Codes::BOTSWANA                         => '🇧🇼',
		Country_Codes::BELARUS                          => '🇧🇾',
		Country_Codes::BELIZE                           => '🇧🇿',
		Country_Codes::CANADA                           => '🇨🇦',
		'CC'                                            => '🇨🇨',
		Country_Codes::DEMOCRATIC_REPUBLIC_OF_THE_CONGO => '🇨🇩',
		Country_Codes::CENTRAL_AFRICAN_REPUBLIC         => '🇨🇫',
		Country_Codes::CONGO                            => '🇨🇬',
		'CH'                                            => '🇨🇭',
		Country_Codes::IVORY_COAST                                            => '🇨🇮',
		'CK'                                            => '🇨🇰',
		Country_Codes::CHILE                            => '🇨🇱',
		Country_Codes::CAMEROON                         => '🇨🇲',
		Country_Codes::CHINA                            => '🇨🇳',
		Country_Codes::COLOMBIA                         => '🇨🇴',
		Country_Codes::COSTA_RICA                       => '🇨🇷',
		Country_Codes::CUBA                             => '🇨🇺',
		Country_Codes::CABO_VERDE                       => '🇨🇻',
		'CW'                                            => '🇨🇼',
		'CX'                                            => '🇨🇽',
		Country_Codes::CYPRUS                           => '🇨🇾',
		Country_Codes::CZECHIA                          => '🇨🇿',
		Country_Codes::GERMANY                                            => '🇩🇪',
		Country_Codes::DJIBOUTI                         => '🇩🇯',
		Country_Codes::DENMARK                          => '🇩🇰',
		Country_Codes::DOMINICA                         => '🇩🇲',
		Country_Codes::DOMINICAN_REPUBLIC               => '🇩🇴',
		Country_Codes::ALGERIA                          => '🇩🇿',
		Country_Codes::ECUADOR                          => '🇪🇨',
		Country_Codes::ESTONIA                          => '🇪🇪',
		Country_Codes::EGYPT                            => '🇪🇬',
		'EH'                                            => '🇪🇭',
		Country_Codes::ERITREA                          => '🇪🇷',
		'ES'                                            => '🇪🇸',
		Country_Codes::ETHIOPIA                         => '🇪🇹',
		'EU'                                            => '🇪🇺',
		Country_Codes::FINLAND                          => '🇫🇮',
		Country_Codes::FIJI                             => '🇫🇯',
		'FK'                                            => '🇫🇰',
		Country_Codes::MICRONESIA                                            => '🇫🇲',
		'FO'                                            => '🇫🇴',
		Country_Codes::FRANCE                           => '🇫🇷',
		Country_Codes::GABON                                            => '🇬🇦',
		'GB'                                            => '🇬🇧',
		Country_Codes::GRENADA                                            => '🇬🇩',
		Country_Codes::GEORGIA                                            => '🇬🇪',
		'GF'                                            => '🇬🇫',
		'GG'                                            => '🇬🇬',
		Country_Codes::GHANA                                            => '🇬🇭',
		'GI'                                            => '🇬🇮',
		'GL'                                            => '🇬🇱',
		Country_Codes::GAMBIA                                            => '🇬🇲',
		Country_Codes::GUINEA                                            => '🇬🇳',
		'GP'                                            => '🇬🇵',
		Country_Codes::EQUATORIAL_GUINEA                => '🇬🇶',
		Country_Codes::GREECE                                            => '🇬🇷',
		'GS'                                            => '🇬🇸',
		Country_Codes::GUATEMALA                                            => '🇬🇹',
		'GU'                                            => '🇬🇺',
		Country_Codes::GUINEA_BISSAU                                            => '🇬🇼',
		Country_Codes::GUYANA                                            => '🇬🇾',
		'HK'                                            => '🇭🇰',
		'HM'                                            => '🇭🇲',
		Country_Codes::HONDURAS                                            => '🇭🇳',
		Country_Codes::CROATIA                          => '🇭🇷',
		Country_Codes::HAITI                                            => '🇭🇹',
		Country_Codes::HUNGARY                                            => '🇭🇺',
		Country_Codes::INDONESIA                                            => '🇮🇩',
		Country_Codes::IRELAND                                            => '🇮🇪',
		Country_Codes::ISRAEL                                            => '🇮🇱',
		'IM'                                            => '🇮🇲',
		Country_Codes::INDIA                                            => '🇮🇳',
		'IO'                                            => '🇮🇴',
		Country_Codes::IRAQ                                            => '🇮🇶',
		Country_Codes::IRAN                                            => '🇮🇷',
		Country_Codes::ICELAND                                            => '🇮🇸',
		Country_Codes::ITALY                                            => '🇮🇹',
		'JE'                                            => '🇯🇪',
		Country_Codes::JAMAICA                                            => '🇯🇲',
		Country_Codes::JORDAN                                            => '🇯🇴',
		Country_Codes::JAPAN                                            => '🇯🇵',
		Country_Codes::KENYA                                            => '🇰🇪',
		Country_Codes::KYRGYZSTAN                                            => '🇰🇬',
		Country_Codes::CAMBODIA                         => '🇰🇭',
		Country_Codes::KIRIBATI                                            => '🇰🇮',
		Country_Codes::COMOROS                          => '🇰🇲',
		'KN'                                            => '🇰🇳',
		Country_Codes::NORTH_KOREA                                            => '🇰🇵',
		'KR'                                            => '🇰🇷',
		Country_Codes::KUWAIT                                            => '🇰🇼',
		'KY'                                            => '🇰🇾',
		Country_Codes::KAZAKHSTAN                                            => '🇰🇿',
		Country_Codes::LAOS                                            => '🇱🇦',
		Country_Codes::LEBANON                                            => '🇱🇧',
		'LC'                                            => '🇱🇨',
		Country_Codes::LIECHTENSTEIN                                            => '🇱🇮',
		'LK'                                            => '🇱🇰',
		Country_Codes::LIBERIA                                            => '🇱🇷',
		Country_Codes::LESOTHO                                            => '🇱🇸',
		Country_Codes::LITHUANIA                                            => '🇱🇹',
		Country_Codes::LUXEMBOURG                                            => '🇱🇺',
		Country_Codes::LATVIA                                            => '🇱🇻',
		Country_Codes::LIBYA                                            => '🇱🇾',
		Country_Codes::MOROCCO                                            => '🇲🇦',
		Country_Codes::MONACO                                            => '🇲🇨',
		Country_Codes::MOLDOVA                                            => '🇲🇩',
		Country_Codes::MONTENEGRO                                            => '🇲🇪',
		'MF'                                            => '🇲🇫',
		Country_Codes::MADAGASCAR                                            => '🇲🇬',
		Country_Codes::MARSHALL_ISLANDS                                            => '🇲🇭',
		Country_Codes::NORTH_MACEDONIA                                            => '🇲🇰',
		Country_Codes::MALI                                            => '🇲🇱',
		Country_Codes::MYANMAR                                            => '🇲🇲',
		Country_Codes::MONGOLIA                                            => '🇲🇳',
		'MO'                                            => '🇲🇴',
		'MP'                                            => '🇲🇵',
		'MQ'                                            => '🇲🇶',
		Country_Codes::MAURITANIA                                            => '🇲🇷',
		'MS'                                            => '🇲🇸',
		Country_Codes::MALTA                                            => '🇲🇹',
		Country_Codes::MAURITIUS                                            => '🇲🇺',
		Country_Codes::MALDIVES                                            => '🇲🇻',
		Country_Codes::MALAWI                                            => '🇲🇼',
		Country_Codes::MEXICO                                            => '🇲🇽',
		Country_Codes::MALAYSIA                                            => '🇲🇾',
		Country_Codes::MOZAMBIQUE                                            => '🇲🇿',
		Country_Codes::NAMIBIA                                            => '🇳🇦',
		'NC'                                            => '🇳🇨',
		Country_Codes::NIGER                                            => '🇳🇪',
		'NF'                                            => '🇳🇫',
		Country_Codes::NIGERIA                                            => '🇳🇬',
		Country_Codes::NICARAGUA                                            => '🇳🇮',
		Country_Codes::NETHERLANDS                                            => '🇳🇱',
		Country_Codes::NORWAY                                            => '🇳🇴',
		Country_Codes::NEPAL                                            => '🇳🇵',
		Country_Codes::NAURU                                            => '🇳🇷',
		'NU'                                            => '🇳🇺',
		Country_Codes::NEW_ZEALAND                                            => '🇳🇿',
		Country_Codes::OMAN                                            => '🇴🇲',
		Country_Codes::PANAMA                                            => '🇵🇦',
		Country_Codes::PERU                                            => '🇵🇪',
		'PF'                                            => '🇵🇫',
		Country_Codes::PAPUA_NEW_GUINEA                                            => '🇵🇬',
		Country_Codes::PHILIPPINES                                            => '🇵🇭',
		Country_Codes::PAKISTAN                                            => '🇵🇰',
		Country_Codes::POLAND                                            => '🇵🇱',
		'PM'                                            => '🇵🇲',
		'PN'                                            => '🇵🇳',
		'PR'                                            => '🇵🇷',
		Country_Codes::PALESTINE                                            => '🇵🇸',
		Country_Codes::PORTUGAL                                            => '🇵🇹',
		Country_Codes::PALAU                                            => '🇵🇼',
		Country_Codes::PARAGUAY                                            => '🇵🇾',
		'QA'                                            => '🇶🇦',
		'RE'                                            => '🇷🇪',
		'RO'                                            => '🇷🇴',
		'RS'                                            => '🇷🇸',
		'RU'                                            => '🇷🇺',
		'RW'                                            => '🇷🇼',
		'SA'                                            => '🇸🇦',
		'SB'                                            => '🇸🇧',
		'SC'                                            => '🇸🇨',
		'SD'                                            => '🇸🇩',
		'SE'                                            => '🇸🇪',
		'SG'                                            => '🇸🇬',
		'SH'                                            => '🇸🇭',
		'SI'                                            => '🇸🇮',
		'SJ'                                            => '🇸🇯',
		'SK'                                            => '🇸🇰',
		'SL'                                            => '🇸🇱',
		'SM'                                            => '🇸🇲',
		'SN'                                            => '🇸🇳',
		'SO'                                            => '🇸🇴',
		'SR'                                            => '🇸🇷',
		'SS'                                            => '🇸🇸',
		'ST'                                            => '🇸🇹',
		Country_Codes::EL_SALVADOR                      => '🇸🇻',
		'SX'                                            => '🇸🇽',
		'SY'                                            => '🇸🇾',
		Country_Codes::ESWATINI                         => '🇸🇿',
		'TC'                                            => '🇹🇨',
		Country_Codes::CHAD                             => '🇹🇩',
		'TF'                                            => '🇹🇫',
		'TG'                                            => '🇹🇬',
		'TH'                                            => '🇹🇭',
		'TJ'                                            => '🇹🇯',
		'TK'                                            => '🇹🇰',
		Country_Codes::EAST_TIMOR                       => '🇹🇱',
		'TM'                                            => '🇹🇲',
		'TN'                                            => '🇹🇳',
		'TO'                                            => '🇹🇴',
		'TR'                                            => '🇹🇷',
		'TT'                                            => '🇹🇹',
		'TV'                                            => '🇹🇻',
		'TW'                                            => '🇹🇼',
		'TZ'                                            => '🇹🇿',
		'UA'                                            => '🇺🇦',
		'UG'                                            => '🇺🇬',
		'UM'                                            => '🇺🇲',
		'US'                                            => '🇺🇸',
		'UY'                                            => '🇺🇾',
		'UZ'                                            => '🇺🇿',
		'VA'                                            => '🇻🇦',
		'VC'                                            => '🇻🇨',
		'VE'                                            => '🇻🇪',
		'VG'                                            => '🇻🇬',
		'VI'                                            => '🇻🇮',
		'VN'                                            => '🇻🇳',
		'VU'                                            => '🇻🇺',
		'WF'                                            => '🇼🇫',
		'WS'                                            => '🇼🇸',
		Country_Codes::KOSOVO                                            => '🇽🇰',
		'YE'                                            => '🇾🇪',
		'YT'                                            => '🇾🇹',
		'ZA'                                            => '🇿🇦',
		'ZM'                                            => '🇿🇲',
		'ZW'                                            => '🇿🇼',
	];

	/**
	 * Retrieves a flag by country code.
	 *
	 * @param string $country country alpha-2 code (ISO 3166) like US.
	 * @return string
	 */
	public static function get_by_country( string $country ): string {
		return self::EMOJI_COUNTRIES_FLAGS[ $country ] ?? '';
	}

	/**
	 * Retrieves a flag by currency code.
	 *
	 * @param string $currency currency code (ISO 4217) like USD.
	 * @return string
	 */
	public static function get_by_currency( string $currency ): string {
		$exceptions = [
			'ANG' => '',
			'BTC' => '',
			'XAF' => '',
			'XCD' => '',
			'XOF' => '',
			'XPF' => '',
		];

		$flag = $exceptions[ $currency ] ?? self::get_by_country( substr( $currency, 0, -1 ) );

		return $flag;
	}
}
