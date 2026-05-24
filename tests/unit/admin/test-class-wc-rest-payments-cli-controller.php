<?php
/**
 * WC_REST_Payments_CLI_Controller tests.
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * Class WC_REST_Payments_CLI_Controller_Test
 *
 * @package WooCommerce\Payments\Tests
 */

require_once WCPAY_ABSPATH . 'includes/admin/class-wc-rest-payments-cli-controller.php';

/**
 * WC_REST_Payments_CLI_Controller unit tests.
 */
class WC_REST_Payments_CLI_Controller_Test extends WCPAY_UnitTestCase {
	/**
	 * Controller under test.
	 *
	 * @var WC_REST_Payments_CLI_Controller
	 */
	private $controller;

	/**
	 * Previous user ID.
	 *
	 * @var int
	 */
	private $previous_user_id;

	public function set_up() {
		parent::set_up();
		$this->controller       = new WC_REST_Payments_CLI_Controller();
		$this->previous_user_id = get_current_user_id();
		delete_option( 'wcpay_cli_authorizations' );
	}

	public function tear_down() {
		delete_option( 'wcpay_cli_authorizations' );
		wp_set_current_user( $this->previous_user_id );
		parent::tear_down();
	}

	public function test_register_routes_registers_cli_endpoints(): void {
		$this->setExpectedIncorrectUsage( 'register_rest_route' );
		$this->controller->register_routes();

		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/wc/v3/payments/cli/authorize', $routes );
		$this->assertArrayHasKey( '/wc/v3/payments/cli/token', $routes );
	}

	public function test_authorize_rejects_insecure_non_local_callback_url(): void {
		$response = $this->controller->authorize(
			$this->create_authorize_request(
				[
					'callback_url' => 'http://example.com/callback',
				]
			)
		);

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( 'wcpay_cli_invalid_callback_url', $response->get_error_code() );
	}

	public function test_authorize_rejects_https_callback_url(): void {
		$response = $this->controller->authorize(
			$this->create_authorize_request(
				[
					'callback_url' => 'https://example.com/callback',
				]
			)
		);

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( 'wcpay_cli_invalid_callback_url', $response->get_error_code() );
	}

	public function test_authorize_rejects_invalid_scope(): void {
		$response = $this->controller->authorize(
			$this->create_authorize_request(
				[
					'scope' => 'admin',
				]
			)
		);

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( 'wcpay_cli_invalid_scope', $response->get_error_code() );
	}

	public function test_authorize_rejects_short_state(): void {
		$response = $this->controller->authorize(
			$this->create_authorize_request(
				[
					'state' => 'short',
				]
			)
		);

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( 'wcpay_cli_invalid_state', $response->get_error_code() );
	}

	public function test_authorize_returns_login_wrapped_authorize_url_for_valid_input(): void {
		$response = $this->controller->authorize( $this->create_authorize_request() );
		$data     = $response->get_data();

		$this->assertStringStartsWith( wp_login_url(), $data['authorize_url'] );
		$this->assertStringContainsString( rawurlencode( admin_url( 'admin-post.php' ) ), $data['authorize_url'] );
		$this->assertStringContainsString( rawurlencode( 'action=wcpay_cli_authorize' ), $data['authorize_url'] );
		$this->assertNotEmpty( $data['expires_at'] );
		$this->assertCount( 1, get_option( 'wcpay_cli_authorizations', [] ) );
	}

	public function test_init_admin_hooks_registers_authenticated_and_unauthenticated_authorize_handlers(): void {
		WC_REST_Payments_CLI_Controller::init_admin_hooks();

		$this->assertNotFalse( has_action( 'admin_post_wcpay_cli_authorize', [ WC_REST_Payments_CLI_Controller::class, 'handle_admin_authorize' ] ) );
		$this->assertNotFalse( has_action( 'admin_post_nopriv_wcpay_cli_authorize', [ WC_REST_Payments_CLI_Controller::class, 'redirect_admin_authorize_to_login' ] ) );
	}

	public function test_admin_approval_requires_manage_woocommerce(): void {
		$subscriber = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber );

		$this->assertFalse( current_user_can( 'manage_woocommerce' ) );
	}

	public function test_token_rejects_invalid_code(): void {
		$response = $this->controller->token( $this->create_token_request( 'invalid-code', 'valid-random-state' ) );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( 'wcpay_cli_invalid_code', $response->get_error_code() );
	}

	public function test_token_rejects_expired_code(): void {
		$this->store_authorized_record(
			'auth-id',
			'valid-code',
			[
				'expires_at' => time() - 1,
			]
		);

		$response = $this->controller->token( $this->create_token_request( 'valid-code', 'valid-random-state' ) );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( 'wcpay_cli_code_expired', $response->get_error_code() );
	}

	public function test_token_rejects_used_code(): void {
		$this->store_authorized_record(
			'auth-id',
			'valid-code',
			[
				'used_at' => time(),
				'status'  => 'used',
			]
		);

		$response = $this->controller->token( $this->create_token_request( 'valid-code', 'valid-random-state' ) );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( 'wcpay_cli_code_used', $response->get_error_code() );
	}

	public function test_token_rejects_mismatched_state(): void {
		$this->store_authorized_record( 'auth-id', 'valid-code' );

		$response = $this->controller->token( $this->create_token_request( 'valid-code', 'different-valid-state' ) );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( 'wcpay_cli_state_mismatch', $response->get_error_code() );
	}

	public function test_token_creates_credentials_and_returns_consumer_key_and_secret(): void {
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->store_authorized_record(
			'auth-id',
			'valid-code',
			[
				'approved_by' => $admin_id,
			]
		);

		$response = $this->controller->token( $this->create_token_request( 'valid-code', 'valid-random-state' ) );
		$data     = $response->get_data();

		$this->assertStringStartsWith( 'ck_', $data['consumer_key'] );
		$this->assertStringStartsWith( 'cs_', $data['consumer_secret'] );
		$this->assertNotEmpty( $data['key_id'] );

		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT user_id, permissions, consumer_key, consumer_secret FROM {$wpdb->prefix}woocommerce_api_keys WHERE key_id = %d",
				$data['key_id']
			),
			ARRAY_A
		);

		$this->assertSame( $admin_id, (int) $row['user_id'] );
		$this->assertSame( 'read_write', $row['permissions'] );
		$this->assertSame( wc_api_hash( $data['consumer_key'] ), $row['consumer_key'] );
		$this->assertSame( $data['consumer_secret'], $row['consumer_secret'] );
	}

	public function test_token_cannot_be_reused(): void {
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->store_authorized_record(
			'auth-id',
			'valid-code',
			[
				'approved_by' => $admin_id,
			]
		);

		$first_response = $this->controller->token( $this->create_token_request( 'valid-code', 'valid-random-state' ) );
		$this->assertNotInstanceOf( WP_Error::class, $first_response );

		$second_response = $this->controller->token( $this->create_token_request( 'valid-code', 'valid-random-state' ) );
		$this->assertInstanceOf( WP_Error::class, $second_response );
		$this->assertSame( 'wcpay_cli_invalid_code', $second_response->get_error_code() );
	}

	private function create_authorize_request( array $overrides = [] ): WP_REST_Request {
		$request = new WP_REST_Request( 'POST', '/wc/v3/payments/cli/authorize' );
		$request->set_body_params(
			array_merge(
				[
					'app_name'     => 'WooPayments CLI',
					'scope'        => 'read_write',
					'state'        => 'valid-random-state',
					'callback_url' => 'http://127.0.0.1:3456/callback',
				],
				$overrides
			)
		);

		return $request;
	}

	private function create_token_request( string $code, string $state ): WP_REST_Request {
		$request = new WP_REST_Request( 'POST', '/wc/v3/payments/cli/token' );
		$request->set_body_params(
			[
				'code'  => $code,
				'state' => $state,
			]
		);

		return $request;
	}

	private function store_authorized_record( string $auth_id, string $code, array $overrides = [] ): void {
		update_option(
			'wcpay_cli_authorizations',
			[
				$auth_id => array_merge(
					[
						'id'           => $auth_id,
						'app_name'     => 'WooPayments CLI',
						'scope'        => 'read_write',
						'state'        => 'valid-random-state',
						'callback_url' => 'http://127.0.0.1:3456/callback',
						'created_at'   => time(),
						'expires_at'   => time() + 300,
						'status'       => 'approved',
						'code_hash'    => wp_hash_password( $code ),
						'approved_by'  => self::factory()->user->create( [ 'role' => 'administrator' ] ),
						'used_at'      => 0,
					],
					$overrides
				),
			]
		);
	}
}
