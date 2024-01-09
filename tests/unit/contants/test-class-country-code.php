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

	public function test_stripe_supported_countries() {
		$this->assertEquals( 'AU', Country_Code::AUSTRALIA );
		$this->assertEquals( 'AT', Country_Code::AUSTRIA );
		$this->assertEquals( 'BE', Country_Code::BELGIUM );
		$this->assertEquals( 'BR', Country_Code::BRAZIL );
		$this->assertEquals( 'BG', Country_Code::BULGARIA );
		$this->assertEquals( 'CA', Country_Code::CANADA );
		$this->assertEquals( 'HR', Country_Code::CROATIA );
		$this->assertEquals( 'CY', Country_Code::CYPRUS );
		$this->assertEquals( 'CZ', Country_Code::CZECHIA );
		$this->assertEquals( 'DK', Country_Code::DENMARK );
		$this->assertEquals( 'EE', Country_Code::ESTONIA );
		$this->assertEquals( 'FI', Country_Code::FINLAND );
		$this->assertEquals( 'FR', Country_Code::FRANCE );
		$this->assertEquals( 'DE', Country_Code::GERMANY );
		$this->assertEquals( 'GH', Country_Code::GHANA );
		$this->assertEquals( 'GI', Country_Code::GIBRALTAR );
		$this->assertEquals( 'GR', Country_Code::GREECE );
		$this->assertEquals( 'HK', Country_Code::HONG_KONG );
		$this->assertEquals( 'HU', Country_Code::HUNGARY );
		$this->assertEquals( 'IN', Country_Code::INDIA );
		$this->assertEquals( 'ID', Country_Code::INDONESIA );
		$this->assertEquals( 'IE', Country_Code::IRELAND );
		$this->assertEquals( 'IT', Country_Code::ITALY );
		$this->assertEquals( 'JP', Country_Code::JAPAN );
		$this->assertEquals( 'KE', Country_Code::KENYA );
		$this->assertEquals( 'LV', Country_Code::LATVIA );
		$this->assertEquals( 'LI', Country_Code::LIECHTENSTEIN );
		$this->assertEquals( 'LT', Country_Code::LITHUANIA );
		$this->assertEquals( 'LU', Country_Code::LUXEMBOURG );
		$this->assertEquals( 'MY', Country_Code::MALAYSIA );
		$this->assertEquals( 'MT', Country_Code::MALTA );
		$this->assertEquals( 'MX', Country_Code::MEXICO );
		$this->assertEquals( 'NL', Country_Code::NETHERLANDS );
		$this->assertEquals( 'NZ', Country_Code::NEW_ZEALAND );
		$this->assertEquals( 'NG', Country_Code::NIGERIA );
		$this->assertEquals( 'NO', Country_Code::NORWAY );
		$this->assertEquals( 'PL', Country_Code::POLAND );
		$this->assertEquals( 'PT', Country_Code::PORTUGAL );
		$this->assertEquals( 'RO', Country_Code::ROMANIA );
		$this->assertEquals( 'SG', Country_Code::SINGAPORE );
		$this->assertEquals( 'SK', Country_Code::SLOVAKIA );
		$this->assertEquals( 'SI', Country_Code::SLOVENIA );
		$this->assertEquals( 'ZA', Country_Code::SOUTH_AFRICA );
		$this->assertEquals( 'ES', Country_Code::SPAIN );
		$this->assertEquals( 'SE', Country_Code::SWEDEN );
		$this->assertEquals( 'CH', Country_Code::SWITZERLAND );
		$this->assertEquals( 'TH', Country_Code::THAILAND );
		$this->assertEquals( 'AE', Country_Code::UNITED_ARAB_EMIRATES );
		$this->assertEquals( 'GB', Country_Code::UNITED_KINGDOM );
		$this->assertEquals( 'US', Country_Code::UNITED_STATES );
	}
}
