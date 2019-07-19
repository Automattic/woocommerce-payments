<?php
/**
 * Class WC_Payments_Dependencies_Validator_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WC_Payments_API_Client unit tests.
 */
class WC_Payments_Dependencies_Validator_Test extends WP_UnitTestCase {

	/**
	 * System under test
	 *
	 * @var WC_Payments_Dependencies_Validator
	 */
	private $dependencies_validator;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();
		$this->dependencies_validator = new WC_Payments_Dependencies_Validator();
	}

	/**
	 * @param string $message Full message.
	 * @param array  $expected_strings List of strings that should be in the message.
	 * @throws Exception On failure.
	 */
	private function assertMessageContains( $message, $expected_strings ) {
		foreach ( $expected_strings as $str ) {
			$this->assertRegExp( '/' . preg_quote( $str, '/' ) . '/', $message );
		}
	}

	/**
	 * @param string $message Full message.
	 * @param array  $expected_strings List of strings that should *not* be in the message.
	 * @throws Exception On failure.
	 */
	private function assertMessageDoesntContain( $message, $expected_strings ) {
		foreach ( $expected_strings as $str ) {
			$this->assertNotRegExp( '/' . preg_quote( $str, '/' ) . '/', $message );
		}
	}

	/**
	 * @throws Exception - In the event of test failure.
	 */
	public function test_get_update_php_error_message() {
		$message = $this->dependencies_validator->get_update_php_error_message( '5.3.3', '5.6' );
		$this->assertMessageContains(
			$message,
			array(
				'PHP 5.6',
				'you are using 5.3.3',
			)
		);
	}

	/**
	 * @throws Exception - In the event of test failure.
	 */
	public function test_get_install_woocommerce_error_message_wc_disabled_can_enable() {
		$message = $this->dependencies_validator->get_install_woocommerce_error_message( true, true );
		$this->assertMessageContains(
			$message,
			array(
				'WooCommerce</a> to be installed',
				'>Activate WooCommerce<',
			)
		);
		$this->assertMessageDoesntContain(
			$message,
			array(
				'>Install WooCommerce<',
			)
		);
	}

	/**
	 * @throws Exception - In the event of test failure.
	 */
	public function test_get_install_woocommerce_error_message_wc_disabled_cant_enable() {
		$message = $this->dependencies_validator->get_install_woocommerce_error_message( false, true );
		$this->assertMessageContains(
			$message,
			array(
				'WooCommerce</a> to be installed',
			)
		);
		$this->assertMessageDoesntContain(
			$message,
			array(
				'>Activate WooCommerce<',
				'>Install WooCommerce<',
			)
		);
	}

	/**
	 * @throws Exception - In the event of test failure.
	 */
	public function test_get_install_woocommerce_error_message_can_install() {
		$message = $this->dependencies_validator->get_install_woocommerce_error_message( true, false );
		$this->assertMessageContains(
			$message,
			array(
				'WooCommerce</a> to be installed',
				'>Install WooCommerce<',
			)
		);
		$this->assertMessageDoesntContain(
			$message,
			array(
				'>Activate WooCommerce<',
			)
		);
	}

	/**
	 * @throws Exception - In the event of test failure.
	 */
	public function test_get_install_woocommerce_error_message_cant_install() {
		$message = $this->dependencies_validator->get_install_woocommerce_error_message( false, false );
		$this->assertMessageContains(
			$message,
			array(
				'WooCommerce</a> to be installed',
			)
		);
		$this->assertMessageDoesntContain(
			$message,
			array(
				'>Activate WooCommerce<',
				'>Install WooCommerce<',
			)
		);
	}

	/**
	 * @throws Exception - In the event of test failure.
	 */
	public function test_get_update_woocommerce_error_message_can_update() {
		$message = $this->dependencies_validator->get_update_woocommerce_error_message( '2.6.0', '3.6', false, true );
		$this->assertMessageContains(
			$message,
			array(
				'requires <strong>WooCommerce 3.6</strong> or greater',
				'>Update WooCommerce<',
			)
		);
		$this->assertMessageDoesntContain(
			$message,
			array(
				'is recommended',
			)
		);
	}

	/**
	 * @throws Exception - In the event of test failure.
	 */
	public function test_get_update_woocommerce_error_message_cant_update() {
		$message = $this->dependencies_validator->get_update_woocommerce_error_message( '2.6.0', '3.6', false, false );
		$this->assertMessageContains(
			$message,
			array(
				'requires <strong>WooCommerce 3.6</strong> or greater',
			)
		);
		$this->assertMessageDoesntContain(
			$message,
			array(
				'>Update WooCommerce<',
				'is recommended',
			)
		);
	}

	/**
	 * @throws Exception - In the event of test failure.
	 */
	public function test_get_update_woocommerce_error_message_recommended_version_can_update() {
		$message = $this->dependencies_validator->get_update_woocommerce_error_message( '2.6.0', '3.6', '4.0', true );
		$this->assertMessageContains(
			$message,
			array(
				'requires <strong>WooCommerce 3.6</strong> or greater',
				'<strong>WooCommerce 4.0</strong> or greater is recommended',
				'>Update WooCommerce<',
			)
		);
	}

	/**
	 * @throws Exception - In the event of test failure.
	 */
	public function test_get_update_woocommerce_error_message_recommended_version_cant_update() {
		$message = $this->dependencies_validator->get_update_woocommerce_error_message( '2.6.0', '3.6', '4.0', false );
		$this->assertMessageContains(
			$message,
			array(
				'requires <strong>WooCommerce 3.6</strong> or greater',
				'<strong>WooCommerce 4.0</strong> or greater is recommended',
			)
		);
		$this->assertMessageDoesntContain(
			$message,
			array(
				'>Update WooCommerce<',
			)
		);
	}

	/**
	 * @throws Exception - In the event of test failure.
	 */
	public function test_get_update_wordpress_error_message_can_update() {
		$message = $this->dependencies_validator->get_update_wordpress_error_message( '4.5', '5.2', true );
		$this->assertMessageContains(
			$message,
			array(
				'WordPress 5.2',
				'you are using 4.5',
				'>Update WordPress<',
			)
		);
		$this->assertMessageDoesntContain(
			$message,
			array(
				'>Update WooCommerce<',
			)
		);
	}

	/**
	 * @throws Exception - In the event of test failure.
	 */
	public function test_get_update_wordpress_error_message_cant_update() {
		$message = $this->dependencies_validator->get_update_wordpress_error_message( '4.5', '5.2', false );
		$this->assertMessageContains(
			$message,
			array(
				'WordPress 5.2',
				'you are using 4.5',
			)
		);
		$this->assertMessageDoesntContain(
			$message,
			array(
				'>Update WordPress<',
			)
		);
	}

	/**
	 * @throws Exception - In the event of test failure.
	 */
	public function test_get_install_wc_admin_error_message_can_install() {
		$message = $this->dependencies_validator->get_install_wc_admin_error_message( '3.6', false, true, false );
		$this->assertMessageContains(
			$message,
			array(
				'WooCommerce Admin</a> to be installed',
				'>Install WooCommerce Admin<',
			)
		);
		$this->assertMessageDoesntContain(
			$message,
			array(
				'requires <strong>WooCommerce 3.6</strong> or greater',
				'>Update WooCommerce<',
				'you can also use',
				'>Activate WooCommerce Admin<',
			)
		);
	}

	/**
	 * @throws Exception - In the event of test failure.
	 */
	public function test_get_install_wc_admin_error_message_cant_install() {
		$message = $this->dependencies_validator->get_install_wc_admin_error_message( '3.6', false, false, false );
		$this->assertMessageContains(
			$message,
			array(
				'WooCommerce Admin</a> to be installed',
			)
		);
		$this->assertMessageDoesntContain(
			$message,
			array(
				'requires <strong>WooCommerce 3.6</strong> or greater',
				'>Update WooCommerce<',
				'you can also use',
				'>Install WooCommerce Admin<',
				'>Activate WooCommerce Admin<',
			)
		);
	}

	/**
	 * @throws Exception - In the event of test failure.
	 */
	public function test_get_install_wc_admin_error_message_wc_admin_disabled_can_enable() {
		$message = $this->dependencies_validator->get_install_wc_admin_error_message( '3.6', false, true, true );
		$this->assertMessageContains(
			$message,
			array(
				'WooCommerce Admin</a> to be installed',
				'>Activate WooCommerce Admin<',
			)
		);
		$this->assertMessageDoesntContain(
			$message,
			array(
				'requires <strong>WooCommerce 3.6</strong> or greater',
				'>Update WooCommerce<',
				'you can also use',
				'>Install WooCommerce Admin<',
			)
		);
	}

	/**
	 * @throws Exception - In the event of test failure.
	 */
	public function test_get_install_wc_admin_error_message_wc_admin_disabled_cant_enable() {
		$message = $this->dependencies_validator->get_install_wc_admin_error_message( '3.6', false, false, true );
		$this->assertMessageContains(
			$message,
			array(
				'WooCommerce Admin</a> to be installed',
			)
		);
		$this->assertMessageDoesntContain(
			$message,
			array(
				'requires <strong>WooCommerce 3.6</strong> or greater',
				'>Update WooCommerce<',
				'you can also use',
				'>Install WooCommerce Admin<',
				'>Activate WooCommerce Admin<',
			)
		);
	}

	/**
	 * @throws Exception - In the event of test failure.
	 */
	public function test_get_install_wc_admin_error_message_recommended_wc_version_can_install() {
		$message = $this->dependencies_validator->get_install_wc_admin_error_message( '3.6', '4.0', true, false );
		$this->assertMessageContains(
			$message,
			array(
				'requires <strong>WooCommerce 4.0</strong> or greater',
				'>Update WooCommerce<',
				'you can also use',
				'>Install WooCommerce Admin<',
			)
		);
		$this->assertMessageDoesntContain(
			$message,
			array(
				'WooCommerce Admin</a> to be installed',
				'>Activate WooCommerce Admin<',
			)
		);
	}

	/**
	 * @throws Exception - In the event of test failure.
	 */
	public function test_get_install_wc_admin_error_message_recommended_wc_version_cant_install() {
		$message = $this->dependencies_validator->get_install_wc_admin_error_message( '3.6', '4.0', false, false );
		$this->assertMessageContains(
			$message,
			array(
				'requires <strong>WooCommerce 4.0</strong> or greater',
				'you can also use',
			)
		);
		$this->assertMessageDoesntContain(
			$message,
			array(
				'WooCommerce Admin</a> to be installed',
				'>Update WooCommerce<',
				'>Install WooCommerce Admin<',
				'>Activate WooCommerce Admin<',
			)
		);
	}

	/**
	 * @throws Exception - In the event of test failure.
	 */
	public function test_get_install_wc_admin_error_message_recommended_wc_version_wc_admin_disabled_can_enable() {
		$message = $this->dependencies_validator->get_install_wc_admin_error_message( '3.6', '4.0', true, true );
		$this->assertMessageContains(
			$message,
			array(
				'requires <strong>WooCommerce 4.0</strong> or greater',
				'>Update WooCommerce<',
				'you can also use',
				'>Activate WooCommerce Admin<',
			)
		);
		$this->assertMessageDoesntContain(
			$message,
			array(
				'WooCommerce Admin</a> to be installed',
				'>Install WooCommerce Admin<',
			)
		);
	}

	/**
	 * @throws Exception - In the event of test failure.
	 */
	public function test_get_install_wc_admin_error_message_recommended_wc_version_wc_admin_disabled_cant_enable() {
		$message = $this->dependencies_validator->get_install_wc_admin_error_message( '3.6', '4.0', false, true );
		$this->assertMessageContains(
			$message,
			array(
				'requires <strong>WooCommerce 4.0</strong> or greater',
				'you can also use',
			)
		);
		$this->assertMessageDoesntContain(
			$message,
			array(
				'WooCommerce Admin</a> to be installed',
				'>Update WooCommerce<',
				'>Install WooCommerce Admin<',
				'>Activate WooCommerce Admin<',
			)
		);
	}

	/**
	 * @throws Exception - In the event of test failure.
	 */
	public function test_get_update_wc_admin_error_message_can_update() {
		$message = $this->dependencies_validator->get_update_wc_admin_error_message( '0.14.0', '0.15', '3.6', false, true );
		$this->assertMessageContains(
			$message,
			array(
				'WooCommerce Admin 0.15',
				'>Update WooCommerce Admin<',
			)
		);
		$this->assertMessageDoesntContain(
			$message,
			array(
				'requires <strong>WooCommerce 3.6</strong> or greater',
				'>Update WooCommerce<',
				'>Activate WooCommerce Admin<',
				'you can also update',
			)
		);
	}

	/**
	 * @throws Exception - In the event of test failure.
	 */
	public function test_get_update_wc_admin_error_message_cant_update() {
		$message = $this->dependencies_validator->get_update_wc_admin_error_message( '0.14.0', '0.15', '3.6', false, false );
		$this->assertMessageContains(
			$message,
			array(
				'WooCommerce Admin 0.15',
			)
		);
		$this->assertMessageDoesntContain(
			$message,
			array(
				'>Update WooCommerce Admin<',
				'requires <strong>WooCommerce 3.6</strong> or greater',
				'>Update WooCommerce<',
				'>Activate WooCommerce Admin<',
				'you can also update',
			)
		);
	}

	/**
	 * @throws Exception - In the event of test failure.
	 */
	public function test_get_update_wc_admin_error_message_recommended_wc_version_can_update() {
		$message = $this->dependencies_validator->get_update_wc_admin_error_message( '0.14.0', '0.15', '3.6', '4.0', true );
		$this->assertMessageContains(
			$message,
			array(
				'requires <strong>WooCommerce 4.0</strong> or greater',
				'>Update WooCommerce<',
				'you can also update',
				'>Update WooCommerce Admin<',
			)
		);
		$this->assertMessageDoesntContain(
			$message,
			array(
				'WooCommerce Admin 0.15',
				'>Activate WooCommerce Admin<',
			)
		);
	}

	/**
	 * @throws Exception - In the event of test failure.
	 */
	public function test_get_update_wc_admin_error_message_recommended_wc_version_cant_update() {
		$message = $this->dependencies_validator->get_update_wc_admin_error_message( '0.14.0', '0.15', '3.6', '4.0', false );
		$this->assertMessageContains(
			$message,
			array(
				'requires <strong>WooCommerce 4.0</strong> or greater',
				'you can also update',
			)
		);
		$this->assertMessageDoesntContain(
			$message,
			array(
				'>Update WooCommerce<',
				'>Update WooCommerce Admin<',
				'>Activate WooCommerce Admin<',
				'WooCommerce Admin 0.15',
			)
		);
	}
}
