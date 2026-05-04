<?php
/**
 * Class AbilitiesRegistrarTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Abilities;

use ReflectionClass;
use WCPAY_UnitTestCase;
use WCPay\Internal\Abilities\Abilities_Registrar;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Tests for the Abilities API registrar.
 */
class AbilitiesRegistrarTest extends WCPAY_UnitTestCase {

	/**
	 * Captured WP_REST_Request from the intercepted dispatch.
	 *
	 * @var WP_REST_Request|null
	 */
	private $captured_request;

	/**
	 * Stub response payload returned by the fake delegated route.
	 *
	 * @var mixed
	 */
	private $stub_response;

	/**
	 * Reset captured state and detach any hooks registered between tests.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->captured_request = null;
		$this->stub_response    = [];

		remove_all_actions( 'wp_abilities_api_categories_init' );
		remove_all_actions( 'wp_abilities_api_init' );
	}

	/**
	 * init() should attach both Abilities API init hooks.
	 */
	public function test_init_attaches_categories_and_abilities_hooks() {
		Abilities_Registrar::init();

		$this->assertNotFalse(
			has_action(
				'wp_abilities_api_categories_init',
				[ Abilities_Registrar::class, 'register_categories' ]
			),
			'register_categories should be hooked into wp_abilities_api_categories_init.'
		);
		$this->assertNotFalse(
			has_action(
				'wp_abilities_api_init',
				[ Abilities_Registrar::class, 'register_abilities' ]
			),
			'register_abilities should be hooked into wp_abilities_api_init.'
		);
	}

	/**
	 * register_categories() should be safe to call without the Abilities API
	 * being present — it must not error or warn on older WordPress.
	 */
	public function test_register_categories_is_safe_without_abilities_api() {
		if ( function_exists( 'wp_register_ability_category' ) ) {
			$this->markTestSkipped( 'Abilities API present; covered by integration paths.' );
		}

		Abilities_Registrar::register_categories();

		$this->assertTrue( true, 'register_categories should no-op without errors.' );
	}

	/**
	 * register_abilities() should be safe to call without the Abilities API.
	 */
	public function test_register_abilities_is_safe_without_abilities_api() {
		if ( function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'Abilities API present; covered by integration paths.' );
		}

		Abilities_Registrar::register_abilities();

		$this->assertTrue( true, 'register_abilities should no-op without errors.' );
	}

	/**
	 * Permission check should reflect manage_woocommerce capability.
	 */
	public function test_permission_callback_requires_manage_woocommerce() {
		$shop_manager = self::factory()->user->create_and_get( [ 'role' => 'shop_manager' ] );
		$subscriber   = self::factory()->user->create_and_get( [ 'role' => 'subscriber' ] );

		wp_set_current_user( $subscriber->ID );
		$this->assertFalse(
			Abilities_Registrar::check_permission_get_transactions(),
			'Subscribers must not pass the get-transactions permission check.'
		);

		wp_set_current_user( $shop_manager->ID );
		$this->assertTrue(
			Abilities_Registrar::check_permission_get_transactions(),
			'Shop managers (manage_woocommerce) must pass the get-transactions permission check.'
		);
	}

	/**
	 * execute_get_transactions() with no input should hit the list route and
	 * forward params as query args.
	 */
	public function test_execute_get_transactions_routes_to_list_endpoint() {
		$this->stub_response = [ [ 'transaction_id' => 'txn_abc' ] ];
		$this->intercept_transactions_routes();

		$result = Abilities_Registrar::execute_get_transactions(
			[
				'page'     => 2,
				'per_page' => 10,
				'type'     => 'charge',
			]
		);

		$this->assertNotNull( $this->captured_request, 'Delegated route should have been invoked.' );
		$this->assertSame( 'GET', $this->captured_request->get_method() );
		$this->assertSame( '/wc/v3/payments/reports/transactions', $this->captured_request->get_route() );
		$this->assertSame( 2, (int) $this->captured_request->get_param( 'page' ) );
		$this->assertSame( 10, (int) $this->captured_request->get_param( 'per_page' ) );
		$this->assertSame( 'charge', $this->captured_request->get_param( 'type' ) );
		$this->assertSame( $this->stub_response, $result );
	}

	/**
	 * Supplying transaction_id should route to the singular endpoint and not
	 * leak the transaction_id back into the query params.
	 */
	public function test_execute_get_transactions_routes_to_singular_endpoint_when_transaction_id_set() {
		$this->stub_response = [ 'transaction_id' => 'txn_xyz' ];
		$this->intercept_transactions_routes();

		$result = Abilities_Registrar::execute_get_transactions(
			[
				'transaction_id' => 'txn_xyz',
				'per_page'       => 50,
			]
		);

		$this->assertNotNull( $this->captured_request );
		$this->assertSame( '/wc/v3/payments/reports/transactions/txn_xyz', $this->captured_request->get_route() );
		$this->assertNull(
			$this->captured_request->get_param( 'transaction_id' ),
			'transaction_id should not leak into query params on the singular route.'
		);
		$this->assertSame( [ $this->stub_response ], $result );
	}

	/**
	 * Errors from the delegated route must be returned as WP_Error.
	 */
	public function test_execute_get_transactions_returns_wp_error_on_delegated_failure() {
		add_filter(
			'rest_pre_dispatch',
			function ( $response, $server, $request ) {
				if ( '/wc/v3/payments/reports/transactions' === $request->get_route() ) {
					return new \WP_Error( 'rest_test_failure', 'simulated', [ 'status' => 500 ] );
				}
				return $response;
			},
			10,
			3
		);

		$result = Abilities_Registrar::execute_get_transactions( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'rest_test_failure', $result->get_error_code() );
	}

	/**
	 * Input schema should advertise the documented type enum.
	 */
	public function test_input_schema_advertises_transaction_type_enum() {
		$schema = $this->invoke_private_method( 'get_transactions_input_schema' );

		$this->assertSame( 'object', $schema['type'] );
		$this->assertArrayHasKey( 'transaction_id', $schema['properties'] );
		$this->assertArrayHasKey( 'type', $schema['properties'] );

		$expected_types = [
			'charge',
			'payment',
			'payment_failure_refund',
			'payment_refund',
			'refund',
			'refund_failure',
			'dispute',
			'dispute_reversal',
			'card_reader_fee',
			'financing_payout',
			'financing_paydown',
			'fee_refund',
		];
		$this->assertSame( $expected_types, $schema['properties']['type']['enum'] );
		$this->assertSame( [ 'asc', 'desc' ], $schema['properties']['direction']['enum'] );
	}

	/**
	 * Output schema should always be an array of transaction objects so the
	 * singular and list paths share one shape.
	 */
	public function test_output_schema_is_an_array_of_transactions() {
		$schema = $this->invoke_private_method( 'get_transactions_output_schema' );

		$this->assertSame( 'array', $schema['type'] );
		$this->assertArrayHasKey( 'items', $schema );
		$this->assertSame( 'object', $schema['items']['type'] );
		$this->assertArrayHasKey( 'transaction_id', $schema['items']['properties'] );
	}

	/**
	 * The singular route returns a single object; the ability must wrap it in
	 * an array to match the documented output schema.
	 */
	public function test_singular_response_is_normalised_to_array() {
		$this->stub_response = [ 'transaction_id' => 'txn_only' ];
		$this->intercept_transactions_routes();

		$result = Abilities_Registrar::execute_get_transactions( [ 'transaction_id' => 'txn_only' ] );

		$this->assertSame( [ [ 'transaction_id' => 'txn_only' ] ], $result );
	}

	/**
	 * Singular fetch with a missing record should normalise to an empty array,
	 * not propagate the controller's "empty array means not found" idiom.
	 */
	public function test_singular_response_is_empty_array_when_not_found() {
		$this->stub_response = [];
		$this->intercept_transactions_routes();

		$result = Abilities_Registrar::execute_get_transactions( [ 'transaction_id' => 'txn_missing' ] );

		$this->assertSame( [], $result );
	}

	/**
	 * Short-circuit dispatching for the transactions REST routes so we can
	 * capture the request and return a stubbed response without depending on
	 * the real REST controller (which requires the WCPay API client and
	 * registers itself on `rest_api_init` ahead of any test-side fakes).
	 */
	private function intercept_transactions_routes() {
		add_filter(
			'rest_pre_dispatch',
			function ( $result, $server, $request ) {
				$route = $request->get_route();
				if ( 0 !== strpos( $route, '/wc/v3/payments/reports/transactions' ) ) {
					return $result;
				}
				$this->captured_request = $request;
				return new WP_REST_Response( $this->stub_response );
			},
			10,
			3
		);
	}

	/**
	 * Reflectively call a private static method on the registrar.
	 *
	 * @param string $method Method name.
	 * @return mixed
	 */
	private function invoke_private_method( $method ) {
		$reflection = new ReflectionClass( Abilities_Registrar::class );
		$reflected  = $reflection->getMethod( $method );
		$reflected->setAccessible( true );
		return $reflected->invoke( null );
	}
}
