<?php
/**
 * Class WC_Payments_Apple_Pay_Registration_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WC_Payments_Apple_Pay_Registration unit tests.
 */
class WC_Payments_Apple_Pay_Registration_Test extends WP_UnitTestCase {

	/**
	 * System under test.
	 *
	 * @var WC_Payments_Apple_Pay_Registration
	 */
	private $wc_apple_pay_registration;

	/**
	 * Mock WC_Payments_API_Client.
	 *
	 * @var WC_Payments_API_Client|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_api_client;

	/**
	 * Domain association file name.
	 *
	 * @var string
	 */
	private $file_name;

	/**
	 * Domain association file contents.
	 *
	 * @var string
	 */
	private $file_contents;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		$this->mock_api_client = $this->getMockBuilder( 'WC_Payments_API_Client' )
			->disableOriginalConstructor()
			->getMock();

		$this->wc_apple_pay_registration = new WC_Payments_Apple_Pay_Registration( $this->mock_api_client );

		$this->file_name     = 'apple-developer-merchantid-domain-association';
		$this->file_contents = file_get_contents( WCPAY_ABSPATH . '/' . $this->file_name ); // @codingStandardsIgnoreLine
	}

	public function tearDown() {
		parent::tearDown();

		$path     = untrailingslashit( ABSPATH );
		$dir      = '.well-known';
		$fullpath = $path . '/' . $dir . '/' . $this->file_name;
		// Unlink domain association file before tests.
		@unlink( $fullpath ); // @codingStandardsIgnoreLine
	}

	public function test_update_domain_association_file() {
		// Unlink domain association file before tests.
		$path     = untrailingslashit( ABSPATH );
		$dir      = '.well-known';
		$fullpath = $path . '/' . $dir . '/' . $this->file_name;

		$this->wc_apple_pay_registration->update_domain_association_file();
		$expected_file_contents = file_get_contents( $fullpath ); // @codingStandardsIgnoreLine

		$this->assertEquals( $expected_file_contents, $this->file_contents );
	}
}
