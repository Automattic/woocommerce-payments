<?php
/**
 * Class WCPay_Core_Request_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Core\Server\Request;
use WCPay\Core\Server\Request\Paginated;
use WCPay\Core\Server\Request\List_Transactions;

// phpcs:disable
class My_Request extends Request {
	const DEFAULT_PARAMS = [
		'default_1' => 1,
	];

	public function get_api(): string {
		return WC_Payments_API_Client::INTENTIONS_API;
	}

	public function get_method(): string {
		return 'POST';
	}

	public function set_param_1( int $value ) {
		$this->set_param( 'param_1', $value );
	}
}
class WooPay_Request extends My_Request {
	const DEFAULT_PARAMS = [
		'default_2' => 2,
	];

	public function set_param_2( int $value ) {
		$this->set_param( 'param_2', $value );
	}
}
class ThirdParty_Request extends My_Request {
	const DEFAULT_PARAMS = [
		'default_3' => 3,
	];

	public function set_param_3( int $value ) {
		$this->set_param( 'param_3', $value );
	}
}
class Another_ThirdParty_Request extends WooPay_Request {
	const DEFAULT_PARAMS = [
		'default_4' => 4,
	];

	public function set_param_4( int $value ) {
		$this->set_param( 'param_4', $value );
	}
}
// phpcs:enable
// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound

/**
 * WCPay\Core\Server\Capture_Intention_Test unit tests.
 */
class WCPay_Core_Request_Test extends WCPAY_UnitTestCase {
	/**
	 * Tests the most basic function of `traverse_class_constants`,
	 * which is to go though all classes in the tree, and return a constant in the right order.
	 */
	public function test_traverse_class_constants() {
		$expected = [];
		$tree     = [
			Request::class,
			Paginated::class,
			List_Transactions::class,
		];
		foreach ( $tree as $class_name ) {
			$expected = array_merge( $expected, constant( $class_name . '::DEFAULT_PARAMS' ) );
		}

		$result = List_Transactions::traverse_class_constants( 'DEFAULT_PARAMS' );
		$this->assertSame( $expected, $result );
	}

	/**
	 * Ensures that `::extend` works with any class, which extends the
	 * base request (where `apply_filters` is called) directly or indirectly.
	 */
	public function test_extension_by_multiple_classes() {
		$hook    = 'some_request_class';
		$request = My_Request::create();
		$request->set_param_1( 1 );

		add_filter(
			$hook,
			function( $request ) {
				$modified = WooPay_Request::extend( $request );
				$modified->set_param_2( 2 );
				return $modified;
			}
		);

		add_filter(
			$hook,
			function( $request ) {
				$modified = ThirdParty_Request::extend( $request );
				$modified->set_param_3( 3 );
				return $modified;
			}
		);

		add_filter(
			$hook,
			function( $request ) {
				$modified = Another_ThirdParty_Request::extend( $request );
				$modified->set_param_4( 4 );
				return $modified;
			}
		);

		$filtered = $request->apply_filters( $hook );
		$result   = $filtered->get_params();

		// Assert: It's important that we got here without exceptions, but everything should be set.
		$this->assertEquals(
			[
				'param_1'   => 1,
				'param_2'   => 2,
				'param_3'   => 3,
				'param_4'   => 4,
				'default_1' => 1,
				'default_2' => 2,
				'default_3' => 3,
				'default_4' => 4,
			],
			$result
		);
	}
}
