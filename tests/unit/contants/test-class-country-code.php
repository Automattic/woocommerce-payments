<?php
/**
 * Class Country_Code_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Constants\Country_Code;

/**
 * Country_Code_Test unit tests.
 */
class Country_Code_Test extends WCPAY_UnitTestCase {

	public function test_africa_country_codes() {
		$this->assertSame( 'DZ', Country_Code::ALGERIA );
		$this->assertSame( 'AO', Country_Code::ANGOLA );
		$this->assertSame( 'BJ', Country_Code::BENIN );
		$this->assertSame( 'BW', Country_Code::BOTSWANA );
		$this->assertSame( 'BF', Country_Code::BURKINA_FASO );
		$this->assertSame( 'BI', Country_Code::BURUNDI );
		$this->assertSame( 'CV', Country_Code::CABO_VERDE );
		$this->assertSame( 'CM', Country_Code::CAMEROON );
		$this->assertSame( 'CF', Country_Code::CENTRAL_AFRICAN_REPUBLIC );
		$this->assertSame( 'TD', Country_Code::CHAD );
		$this->assertSame( 'KM', Country_Code::COMOROS );
		$this->assertSame( 'CG', Country_Code::CONGO );
		$this->assertSame( 'CI', Country_Code::IVORY_COAST );
		$this->assertSame( 'DJ', Country_Code::DJIBOUTI );
		$this->assertSame( 'EG', Country_Code::EGYPT );
		$this->assertSame( 'GQ', Country_Code::EQUATORIAL_GUINEA );
		$this->assertSame( 'ER', Country_Code::ERITREA );
		$this->assertSame( 'SZ', Country_Code::ESWATINI );
		$this->assertSame( 'ET', Country_Code::ETHIOPIA );
		$this->assertSame( 'GA', Country_Code::GABON );
		$this->assertSame( 'GM', Country_Code::GAMBIA );
		$this->assertSame( 'GH', Country_Code::GHANA );
		$this->assertSame( 'GN', Country_Code::GUINEA );
		$this->assertSame( 'GW', Country_Code::GUINEA_BISSAU );
		$this->assertSame( 'KE', Country_Code::KENYA );
		$this->assertSame( 'LS', Country_Code::LESOTHO );
		$this->assertSame( 'LR', Country_Code::LIBERIA );
		$this->assertSame( 'LY', Country_Code::LIBYA );
		$this->assertSame( 'MG', Country_Code::MADAGASCAR );
		$this->assertSame( 'MW', Country_Code::MALAWI );
		$this->assertSame( 'ML', Country_Code::MALI );
		$this->assertSame( 'MR', Country_Code::MAURITANIA );
		$this->assertSame( 'MU', Country_Code::MAURITIUS );
		$this->assertSame( 'MA', Country_Code::MOROCCO );
		$this->assertSame( 'MZ', Country_Code::MOZAMBIQUE );
		$this->assertSame( 'NA', Country_Code::NAMIBIA );
		$this->assertSame( 'NE', Country_Code::NIGER );
		$this->assertSame( 'NG', Country_Code::NIGERIA );
		$this->assertSame( 'RW', Country_Code::RWANDA );
		$this->assertSame( 'ST', Country_Code::SAO_TOME_AND_PRINCIPE );
		$this->assertSame( 'SN', Country_Code::SENEGAL );
		$this->assertSame( 'SC', Country_Code::SEYCHELLES );
		$this->assertSame( 'SL', Country_Code::SIERRA_LEONE );
		$this->assertSame( 'SO', Country_Code::SOMALIA );
		$this->assertSame( 'ZA', Country_Code::SOUTH_AFRICA );
		$this->assertSame( 'SS', Country_Code::SOUTH_SUDAN );
		$this->assertSame( 'SD', Country_Code::SUDAN );
		$this->assertSame( 'TZ', Country_Code::TANZANIA );
		$this->assertSame( 'TG', Country_Code::TOGO );
		$this->assertSame( 'TN', Country_Code::TUNISIA );
		$this->assertSame( 'UG', Country_Code::UGANDA );
		$this->assertSame( 'ZM', Country_Code::ZAMBIA );
		$this->assertSame( 'ZW', Country_Code::ZIMBABWE );
	}

	public function test_europe_country_codes() {
		$this->assertSame( 'AL', Country_Code::ALBANIA );
		$this->assertSame( 'AD', Country_Code::ANDORRA );
		$this->assertSame( 'AT', Country_Code::AUSTRIA );
		$this->assertSame( 'BY', Country_Code::BELARUS );
		$this->assertSame( 'BE', Country_Code::BELGIUM );
		$this->assertSame( 'BA', Country_Code::BOSNIA_AND_HERZEGOVINA );
		$this->assertSame( 'BG', Country_Code::BULGARIA );
		$this->assertSame( 'HR', Country_Code::CROATIA );
		$this->assertSame( 'CY', Country_Code::CYPRUS );
		$this->assertSame( 'CZ', Country_Code::CZECHIA );
		$this->assertSame( 'DK', Country_Code::DENMARK );
		$this->assertSame( 'EE', Country_Code::ESTONIA );
		$this->assertSame( 'FI', Country_Code::FINLAND );
		$this->assertSame( 'FR', Country_Code::FRANCE );
		$this->assertSame( 'GE', Country_Code::GEORGIA );
		$this->assertSame( 'DE', Country_Code::GERMANY );
		$this->assertSame( 'GR', Country_Code::GREECE );
		$this->assertSame( 'HU', Country_Code::HUNGARY );
		$this->assertSame( 'IS', Country_Code::ICELAND );
		$this->assertSame( 'IE', Country_Code::IRELAND );
		$this->assertSame( 'IT', Country_Code::ITALY );
		$this->assertSame( 'XK', Country_Code::KOSOVO );
		$this->assertSame( 'LV', Country_Code::LATVIA );
		$this->assertSame( 'LI', Country_Code::LIECHTENSTEIN );
		$this->assertSame( 'LT', Country_Code::LITHUANIA );
		$this->assertSame( 'LU', Country_Code::LUXEMBOURG );
		$this->assertSame( 'MT', Country_Code::MALTA );
		$this->assertSame( 'MD', Country_Code::MOLDOVA );
		$this->assertSame( 'MC', Country_Code::MONACO );
		$this->assertSame( 'ME', Country_Code::MONTENEGRO );
		$this->assertSame( 'NL', Country_Code::NETHERLANDS );
		$this->assertSame( 'MK', Country_Code::NORTH_MACEDONIA );
		$this->assertSame( 'NO', Country_Code::NORWAY );
		$this->assertSame( 'PL', Country_Code::POLAND );
		$this->assertSame( 'PT', Country_Code::PORTUGAL );
		$this->assertSame( 'RO', Country_Code::ROMANIA );
		$this->assertSame( 'RU', Country_Code::RUSSIA );
		$this->assertSame( 'SM', Country_Code::SAN_MARINO );
		$this->assertSame( 'RS', Country_Code::SERBIA );
		$this->assertSame( 'SK', Country_Code::SLOVAKIA );
		$this->assertSame( 'SI', Country_Code::SLOVENIA );
		$this->assertSame( 'ES', Country_Code::SPAIN );
		$this->assertSame( 'SE', Country_Code::SWEDEN );
		$this->assertSame( 'CH', Country_Code::SWITZERLAND );
		$this->assertSame( 'UA', Country_Code::UKRAINE );
		$this->assertSame( 'GB', Country_Code::UNITED_KINGDOM );
		$this->assertSame( 'VA', Country_Code::VATICAN_CITY );
	}

	public function test_asia_country_codes() {
		$this->assertSame( 'AF', Country_Code::AFGHANISTAN );
		$this->assertSame( 'AM', Country_Code::ARMENIA );
		$this->assertSame( 'AZ', Country_Code::AZERBAIJAN );
		$this->assertSame( 'BH', Country_Code::BAHRAIN );
		$this->assertSame( 'BD', Country_Code::BANGLADESH );
		$this->assertSame( 'BT', Country_Code::BHUTAN );
		$this->assertSame( 'BN', Country_Code::BRUNEI );
		$this->assertSame( 'KH', Country_Code::CAMBODIA );
		$this->assertSame( 'CN', Country_Code::CHINA );
		$this->assertSame( 'GE', Country_Code::GEORGIA );
		$this->assertSame( 'IN', Country_Code::INDIA );
		$this->assertSame( 'ID', Country_Code::INDONESIA );
		$this->assertSame( 'IR', Country_Code::IRAN );
		$this->assertSame( 'IQ', Country_Code::IRAQ );
		$this->assertSame( 'IL', Country_Code::ISRAEL );
		$this->assertSame( 'JP', Country_Code::JAPAN );
		$this->assertSame( 'JO', Country_Code::JORDAN );
		$this->assertSame( 'KZ', Country_Code::KAZAKHSTAN );
		$this->assertSame( 'KW', Country_Code::KUWAIT );
		$this->assertSame( 'KG', Country_Code::KYRGYZSTAN );
		$this->assertSame( 'LA', Country_Code::LAOS );
		$this->assertSame( 'LB', Country_Code::LEBANON );
		$this->assertSame( 'MY', Country_Code::MALAYSIA );
		$this->assertSame( 'MV', Country_Code::MALDIVES );
		$this->assertSame( 'MN', Country_Code::MONGOLIA );
		$this->assertSame( 'MM', Country_Code::MYANMAR );
		$this->assertSame( 'NP', Country_Code::NEPAL );
		$this->assertSame( 'KP', Country_Code::NORTH_KOREA );
		$this->assertSame( 'OM', Country_Code::OMAN );
		$this->assertSame( 'PK', Country_Code::PAKISTAN );
		$this->assertSame( 'PS', Country_Code::PALESTINE );
		$this->assertSame( 'PH', Country_Code::PHILIPPINES );
		$this->assertSame( 'QA', Country_Code::QATAR );
		$this->assertSame( 'SA', Country_Code::SAUDI_ARABIA );
		$this->assertSame( 'SG', Country_Code::SINGAPORE );
		$this->assertSame( 'KR', Country_Code::SOUTH_KOREA );
		$this->assertSame( 'SY', Country_Code::SYRIA );
		$this->assertSame( 'TW', Country_Code::TAIWAN );
		$this->assertSame( 'TJ', Country_Code::TAJIKISTAN );
		$this->assertSame( 'TH', Country_Code::THAILAND );
		$this->assertSame( 'TR', Country_Code::TURKEY );
		$this->assertSame( 'TM', Country_Code::TURKMENISTAN );
		$this->assertSame( 'AE', Country_Code::UNITED_ARAB_EMIRATES );
		$this->assertSame( 'UZ', Country_Code::UZBEKISTAN );
		$this->assertSame( 'VN', Country_Code::VIETNAM );
		$this->assertSame( 'YE', Country_Code::YEMEN );
	}

	public function test_north_america_country_codes() {
		$this->assertSame( 'BS', Country_Code::BAHAMAS );
		$this->assertSame( 'BB', Country_Code::BARBADOS );
		$this->assertSame( 'BZ', Country_Code::BELIZE );
		$this->assertSame( 'BM', Country_Code::BERMUDA );
		$this->assertSame( 'CA', Country_Code::CANADA );
		$this->assertSame( 'CR', Country_Code::COSTA_RICA );
		$this->assertSame( 'CU', Country_Code::CUBA );
		$this->assertSame( 'DM', Country_Code::DOMINICA );
		$this->assertSame( 'DO', Country_Code::DOMINICAN_REPUBLIC );
		$this->assertSame( 'SV', Country_Code::EL_SALVADOR );
		$this->assertSame( 'GD', Country_Code::GRENADA );
		$this->assertSame( 'GT', Country_Code::GUATEMALA );
		$this->assertSame( 'HT', Country_Code::HAITI );
		$this->assertSame( 'HN', Country_Code::HONDURAS );
		$this->assertSame( 'JM', Country_Code::JAMAICA );
		$this->assertSame( 'MX', Country_Code::MEXICO );
		$this->assertSame( 'NI', Country_Code::NICARAGUA );
		$this->assertSame( 'PA', Country_Code::PANAMA );
		$this->assertSame( 'KN', Country_Code::SAINT_KITTS_AND_NEVIS );
		$this->assertSame( 'LC', Country_Code::SAINT_LUCIA );
		$this->assertSame( 'VC', Country_Code::SAINT_VINCENT_AND_THE_GRENADINES );
		$this->assertSame( 'TT', Country_Code::TRINIDAD_AND_TOBAGO );
		$this->assertSame( 'US', Country_Code::UNITED_STATES );
	}

	public function test_south_america_country_codes() {
		$this->assertSame( 'AR', Country_Code::ARGENTINA );
		$this->assertSame( 'BO', Country_Code::BOLIVIA );
		$this->assertSame( 'BR', Country_Code::BRAZIL );
		$this->assertSame( 'CL', Country_Code::CHILE );
		$this->assertSame( 'CO', Country_Code::COLOMBIA );
		$this->assertSame( 'EC', Country_Code::ECUADOR );
		$this->assertSame( 'GY', Country_Code::GUYANA );
		$this->assertSame( 'PY', Country_Code::PARAGUAY );
		$this->assertSame( 'PE', Country_Code::PERU );
		$this->assertSame( 'SR', Country_Code::SURINAME );
		$this->assertSame( 'UY', Country_Code::URUGUAY );
		$this->assertSame( 'VE', Country_Code::VENEZUELA );
	}

	public function test_oceania_country_codes() {
		$this->assertSame( 'AU', Country_Code::AUSTRALIA );
		$this->assertSame( 'CK', Country_Code::COOK_ISLANDS );
		$this->assertSame( 'FJ', Country_Code::FIJI );
		$this->assertSame( 'KI', Country_Code::KIRIBATI );
		$this->assertSame( 'MH', Country_Code::MARSHALL_ISLANDS );
		$this->assertSame( 'FM', Country_Code::MICRONESIA );
		$this->assertSame( 'NR', Country_Code::NAURU );
		$this->assertSame( 'NZ', Country_Code::NEW_ZEALAND );
		$this->assertSame( 'PW', Country_Code::PALAU );
		$this->assertSame( 'PG', Country_Code::PAPUA_NEW_GUINEA );
		$this->assertSame( 'WS', Country_Code::SAMOA );
		$this->assertSame( 'SB', Country_Code::SOLOMON_ISLANDS );
		$this->assertSame( 'TO', Country_Code::TONGA );
		$this->assertSame( 'TV', Country_Code::TUVALU );
		$this->assertSame( 'VU', Country_Code::VANUATU );
	}

	public function test_antarctica_country_codes() {
		$this->assertSame( 'AQ', Country_Code::ANTARCTICA );
	}
}
