<?php
/**
 * Class SettingsScreenServiceTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Service;

use WCPay\Internal\Service\SettingsScreenService;
use WCPAY_UnitTestCase;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Tests the WooPayments settings screen registration and its settings route.
 */
class SettingsScreenServiceTest extends WCPAY_UnitTestCase {
	/**
	 * The REST server before the test.
	 *
	 * @var mixed
	 */
	private $previous_server;

	/**
	 * Service under test.
	 *
	 * @var SettingsScreenService
	 */
	private $sut;

	/**
	 * Registers the settings route next to a stand-in for the existing settings route.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->previous_server     = $GLOBALS['wp_rest_server'];
		$GLOBALS['wp_rest_server'] = new WP_REST_Server();
		$this->sut                 = new SettingsScreenService();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		// phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment -- Runs the WordPress REST lifecycle.
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
						return new WP_REST_Response( [ 'is_debug_log_enabled' => (bool) get_option( 'wcpay_settings_screen_test', false ) ] );
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
						update_option( 'wcpay_settings_screen_test', $request['is_debug_log_enabled'] );
						// The existing route wraps the record in a second response.
						return new WP_REST_Response( new WP_REST_Response( [ 'is_debug_log_enabled' => $request['is_debug_log_enabled'] ] ) );
					},
				],
			],
			true
		);
	}

	/**
	 * Restores the REST server.
	 */
	protected function tearDown(): void {
		$GLOBALS['wp_rest_server'] = $this->previous_server;
		delete_option( 'wcpay_settings_screen_test' );
		parent::tearDown();
	}

	public function test_registers_screen_for_classic_section(): void {
		$screens = $this->sut->register_screen( [ 'other' => [ 'title' => 'Other' ] ] );

		$this->assertArrayHasKey( 'other', $screens, 'Other screens should be kept' );
		$this->assertSame( '/wc/v3/payments/settings-dataform', $screens['woopayments']['rest_path'] );
		$this->assertSame( 'woocommerce_payments', $screens['woopayments']['classic_section'] );
		$this->assertSame( [ 'wcpay-settings-screen' ], $screens['woopayments']['scripts'] );
	}

	/**
	 * @testWith ["woopay", "woopay"]
	 *           ["payment_request", "apple-pay-google-pay"]
	 *           ["amazon_pay", "amazon-pay"]
	 *
	 * @param string $method The classic `method` argument.
	 * @param string $path   The sub-page path.
	 */
	public function test_opens_classic_customisation_links_on_their_sub_page( string $method, string $path ): void {
		$location = $this->sut->get_classic_location(
			[
				'path' => '',
				'args' => [
					'method' => $method,
					'other'  => 'kept',
				],
			],
			'woopayments'
		);

		$this->assertSame(
			[
				'path' => $path,
				'args' => [ 'other' => 'kept' ],
			],
			$location
		);
	}

	public function test_leaves_other_classic_links_unchanged(): void {
		$location = [
			'path' => '',
			'args' => [ 'method' => 'card' ],
		];

		$this->assertSame( $location, $this->sut->get_classic_location( $location, 'woopayments' ) );
		$this->assertSame(
			[
				'path' => '',
				'args' => [ 'method' => 'woopay' ],
			],
			$this->sut->get_classic_location(
				[
					'path' => '',
					'args' => [ 'method' => 'woopay' ],
				],
				'other'
			)
		);
	}

	public function test_save_returns_saved_record(): void {
		update_option( 'wcpay_settings_screen_test', true );
		$request = new WP_REST_Request( 'PUT', '/wc/v3/payments/settings-dataform' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( [ 'is_debug_log_enabled' => false ] ) );

		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( [ 'is_debug_log_enabled' => false ], $response->get_data() );
		$this->assertFalse( (bool) get_option( 'wcpay_settings_screen_test' ) );
	}

	public function test_save_returns_validation_errors_without_saving(): void {
		$request = new WP_REST_Request( 'POST', '/wc/v3/payments/settings-dataform' );
		$request->set_body_params( [ 'is_debug_log_enabled' => [ 'invalid' ] ] );

		$response = rest_do_request( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_invalid_param', $response->get_data()['code'] );
		$this->assertFalse( get_option( 'wcpay_settings_screen_test' ) );
	}

	public function test_denies_customers(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'customer' ] ) );

		$response = rest_do_request( new WP_REST_Request( 'GET', '/wc/v3/payments/settings-dataform' ) );

		$this->assertSame( 403, $response->get_status() );
	}

	public function test_save_keeps_fraud_rules_when_only_protection_level_changes(): void {
		$payload = null;
		register_rest_route(
			'wc/v3',
			'/payments/settings',
			[
				'methods'             => [ 'GET', 'POST' ],
				'permission_callback' => '__return_true',
				'callback'            => function ( $request ) use ( &$payload ) {
					if ( 'POST' === $request->get_method() ) {
						$payload = $request->get_params();
					}
					return new WP_REST_Response( [ 'advanced_fraud_protection_settings' => [ [ 'key' => 'existing-rule' ] ] ] );
				},
			],
			true
		);
		$request = new WP_REST_Request( 'POST', '/wc/v3/payments/settings-dataform' );
		$request->set_body_params( [ 'current_protection_level' => 'advanced' ] );

		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'advanced', $payload['current_protection_level'] );
		$this->assertSame( [ [ 'key' => 'existing-rule' ] ], $payload['advanced_fraud_protection_settings'] );
	}

	public function test_view_config_places_every_field_in_a_card(): void {
		$config = new class() {
			/**
			 * The merged config.
			 *
			 * @var array
			 */
			public $merged = [];

			/**
			 * Records the merged config.
			 *
			 * @param array $config The config.
			 */
			public function merge( array $config ) {
				$this->merged = $config;
				return $this;
			}
		};

		$this->sut->get_view_config( $config );

		// Groups (cards and sub-pages) aren't fields, so collect only the fields inside them.
		$collect  = static function ( array $entries ) use ( &$collect ): array {
			$ids = [];
			foreach ( $entries as $entry ) {
				if ( is_array( $entry ) && isset( $entry['children'] ) ) {
					$ids = array_merge( $ids, $collect( $entry['children'] ) );
				} else {
					$ids[] = is_array( $entry ) ? $entry['id'] : $entry;
				}
			}
			return $ids;
		};
		$in_cards = array_values( array_unique( $collect( $config->merged['form']['fields'] ) ) );
		// The payment methods control shows the express checkouts in its own tabs.
		$in_cards[] = 'is_payment_request_enabled';
		$field_ids  = array_column( $this->sut->get_fields(), 'id' );
		sort( $in_cards );
		sort( $field_ids );
		$this->assertSame( $field_ids, $in_cards );
	}
}
