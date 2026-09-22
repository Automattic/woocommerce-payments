<?php
/**
 * Settings entity REST bridge tests.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Service;

use WCPay\Internal\Service\SettingsDataFormService;
use WCPAY_UnitTestCase;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/** Tests validation and persistence through REST dispatch. */
class SettingsDataFormServiceTest extends WCPAY_UnitTestCase {
	/** @var mixed Previous REST server. */
	private $previous_server;

	/** @var int Previous user. */
	private $previous_user;

	/** @var SettingsDataFormService Service under test. */
	private $sut;

	/** Prepare an isolated endpoint with the same response shape as settings. */
	protected function setUp(): void {
		parent::setUp();
		$this->previous_server     = $GLOBALS['wp_rest_server'];
		$this->previous_user       = get_current_user_id();
		$GLOBALS['wp_rest_server'] = new WP_REST_Server();
		$this->sut                 = new SettingsDataFormService();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		// phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment -- Invoke the existing WordPress lifecycle in this test.
		do_action( 'rest_api_init' );
		$this->sut->register_routes();
		register_rest_route(
			'wc/v3',
			'/payments/settings',
			[
				[
					'methods'             => 'GET',
					'permission_callback' => '__return_true',
					'callback'            => function () {
						return new WP_REST_Response( [ 'is_debug_log_enabled' => (bool) get_option( 'wcpay_dataform_test', false ) ] );
					},
				],
				[
					'methods'             => 'POST',
					'permission_callback' => '__return_true',
					'args'                => [
						'is_debug_log_enabled' => [
							'type'              => 'boolean',
							'validate_callback' => 'rest_validate_request_arg',
						],
					],
					'callback'            => function ( $request ) {
									update_option( 'wcpay_dataform_test', $request['is_debug_log_enabled'] );
									return new WP_REST_Response( new WP_REST_Response( [ 'is_debug_log_enabled' => $request['is_debug_log_enabled'] ] ) );
					},
				],
			],
			true
		);
	}

	/** Restore shared state. */
	protected function tearDown(): void {
		$GLOBALS['wp_rest_server'] = $this->previous_server;
		wp_set_current_user( $this->previous_user );
		delete_option( 'wcpay_dataform_test' );
		parent::tearDown();
	}

	/** A save returns the flat, persisted record, including false. */
	public function test_saves_and_reads_flat_values(): void {
		$request = new WP_REST_Request( 'POST', '/wc/v3/payments/settings-dataform' );
		$request->set_body_params( [ 'is_debug_log_enabled' => false ] );
		update_option( 'wcpay_dataform_test', true );
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( [ 'is_debug_log_enabled' => false ], $response->get_data() );
		$this->assertFalse( (bool) get_option( 'wcpay_dataform_test' ) );
	}

	/** Invalid values still pass through the existing route validation. */
	public function test_rejects_invalid_values_without_saving(): void {
		$request = new WP_REST_Request( 'POST', '/wc/v3/payments/settings-dataform' );
		$request->set_body_params( [ 'is_debug_log_enabled' => [ 'invalid' ] ] );
		$response = rest_do_request( $request );
		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_invalid_param', $response->get_data()['code'] );
		$this->assertFalse( get_option( 'wcpay_dataform_test' ) );
	}

	/** Customers cannot edit payment settings. */
	public function test_denies_customer(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'customer' ] ) );
		$response = rest_do_request( new WP_REST_Request( 'GET', '/wc/v3/payments/settings-dataform' ) );
		$this->assertSame( 403, $response->get_status() );
	}
}
