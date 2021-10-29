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
	 * Mock WC_Payments_Account.
	 *
	 * @var WC_Payments_Account|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_account;

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
	private $initial_file_contents;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		$this->mock_api_client = $this->getMockBuilder( 'WC_Payments_API_Client' )
			->disableOriginalConstructor()
			->getMock();

		$this->mock_account = $this->getMockBuilder( 'WC_Payments_Account' )
			->disableOriginalConstructor()
			->getMock();

		$mock_gateway = $this->getMockBuilder( WC_Payment_Gateway_WCPay::class )
			->disableOriginalConstructor()
			->getMock();

		$this->wc_apple_pay_registration = new WC_Payments_Apple_Pay_Registration( $this->mock_api_client, $this->mock_account, $mock_gateway );

		$this->file_name             = 'apple-developer-merchantid-domain-association';
		$this->initial_file_contents = file_get_contents( WCPAY_ABSPATH . '/' . $this->file_name ); // @codingStandardsIgnoreLine
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
		$path     = untrailingslashit( ABSPATH );
		$dir      = '.well-known';
		$fullpath = $path . '/' . $dir . '/' . $this->file_name;

		$this->wc_apple_pay_registration->update_domain_association_file();
		$updated_file_contents = file_get_contents( $fullpath ); // @codingStandardsIgnoreLine

		$this->assertEquals( $updated_file_contents, $this->initial_file_contents );
	}

	public function test_add_domain_association_rewrite_rule() {
		$this->set_permalink_structure( '/%postname%/' );
		$this->wc_apple_pay_registration->add_domain_association_rewrite_rule();
		flush_rewrite_rules();

		global $wp_rewrite;
		$rewrite_rule = 'index.php?' . $this->file_name . '=1';

		$this->assertContains( $rewrite_rule, $wp_rewrite->rewrite_rules() );
	}

	public function test_it_adds_rewrite_rules_before_init_priority_10() {
		$add_rewrite_rules_callback_priority = has_action(
			'init',
			[ $this->wc_apple_pay_registration, 'add_domain_association_rewrite_rule' ]
		);

		$this->assertInternalType( 'int', $add_rewrite_rules_callback_priority );
		$this->assertLessThan(
			10,
			$add_rewrite_rules_callback_priority
		);
	}

	public function test_it_verifies_domain_during_upgrade() {
		$verify_callback_priority = has_action(
			'woocommerce_woocommerce_payments_updated',
			[ $this->wc_apple_pay_registration, 'verify_domain_on_update' ]
		);

		$this->assertInternalType( 'int', $verify_callback_priority );
	}
}
