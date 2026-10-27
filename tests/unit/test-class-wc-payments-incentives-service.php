<?php
/**
 * Class WC_Payments_Incentives_Service_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WC_Payments_Incentives_Service unit tests.
 *
 * Fetching, caching and eligibility are owned by WooCommerce core, so these tests
 * only stub the incentives API response and cover the WooPayments-side surfaces.
 */
class WC_Payments_Incentives_Service_Test extends WCPAY_UnitTestCase {
	/**
	 * System under test.
	 *
	 * @var WC_Payments_Incentives_Service
	 */
	private $incentives_service;

	/**
	 * Mocked incentive data.
	 *
	 * @var array
	 */
	private $mock_incentive_data;

	/**
	 * Number of requests made to the incentives API.
	 *
	 * @var int
	 */
	private $incentives_api_requests = 0;

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		$this->incentives_service = new WC_Payments_Incentives_Service();
		$this->incentives_service->init_hooks();
		$this->incentives_service->clear_cache();

		$this->mock_incentive_data = [
			'id'          => 'incentive_id',
			'promo_id'    => 'incentive_promo_id',
			'type'        => 'connect_page',
			'description' => 'incentive_description',
			'tc_url'      => 'incentive_tc_url',
		];

		$this->incentives_api_requests = 0;

		// Ensure the Payments menu is present.
		global $menu;
		// phpcs:ignore: WordPress.WP.GlobalVariablesOverride.Prohibited
		$menu = [
			[ 'Payments', null, 'wc-admin&path=/payments/connect' ],
		];
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	public function tear_down() {
		global $menu;
		$menu = null; // phpcs:ignore: WordPress.WP.GlobalVariablesOverride.Prohibited

		remove_all_filters( 'pre_http_request' );

		// WooCommerce core stores these while determining eligibility.
		delete_option( WC_Payments_Incentives_Service::STORE_HAD_WOOPAYMENTS_OPTION_NAME );
		delete_option( WC_Payments_Incentives_Service::STORE_HAD_WOOPAYMENTS_VERSION_OPTION_NAME );
		delete_transient( WC_Payments_Incentives_Service::PREFIX . 'woopayments_store_has_orders' );

		$this->incentives_service->clear_cache();

		parent::tear_down();
	}

	public function test_filters_registered_properly() {
		// Assert.
		$this->assertNotFalse( has_action( 'admin_menu', [ $this->incentives_service, 'add_payments_menu_badge' ] ) );
		$this->assertNotFalse( has_filter( 'woocommerce_admin_allowed_promo_notes', [ $this->incentives_service, 'allowed_promo_notes' ] ) );
		$this->assertNotFalse( has_filter( 'woocommerce_admin_woopayments_onboarding_task_badge', [ $this->incentives_service, 'onboarding_task_badge' ] ) );
	}

	public function test_add_payments_menu_badge_without_incentive() {
		global $menu;

		// Arrange.
		$this->mock_incentives_api_with();

		// Act.
		$this->incentives_service->add_payments_menu_badge();

		// Assert.
		$this->assertSame( 'Payments', $menu[0][0] );
	}

	public function test_add_payments_menu_badge_with_incentive() {
		global $menu;

		// Arrange.
		$this->mock_incentives_api_with( [ $this->mock_incentive_data ] );

		// Act.
		$this->incentives_service->add_payments_menu_badge();

		// Assert.
		$this->assertSame( 'Payments' . WC_Payments_Admin::MENU_NOTIFICATION_BADGE, $menu[0][0] );
	}

	public function test_allowed_promo_notes_without_incentive() {
		// Arrange.
		$this->mock_incentives_api_with();

		// Act.
		$promo_notes = $this->incentives_service->allowed_promo_notes();

		// Assert.
		$this->assertEmpty( $promo_notes );
	}

	public function test_allowed_promo_notes_with_incentive() {
		// Arrange.
		$this->mock_incentives_api_with( [ $this->mock_incentive_data ] );

		// Act.
		$promo_notes = $this->incentives_service->allowed_promo_notes();

		// Assert.
		$this->assertContains( 'incentive_id', $promo_notes );
	}

	public function test_onboarding_task_badge_without_incentive() {
		// Arrange.
		$this->mock_incentives_api_with();

		// Act.
		$badge = $this->incentives_service->onboarding_task_badge( '' );

		// Assert.
		$this->assertEmpty( $badge );
	}

	public function test_onboarding_task_badge_with_incentive_no_task_badge() {
		// Arrange.
		$this->mock_incentives_api_with( [ $this->mock_incentive_data ] );

		// Act.
		$badge = $this->incentives_service->onboarding_task_badge( '' );

		// Assert.
		$this->assertEmpty( $badge );
	}

	public function test_onboarding_task_badge_with_incentive_and_task_badge() {
		// Arrange.
		$incentive_data               = $this->mock_incentive_data;
		$incentive_data['task_badge'] = 'task_badge';
		$this->mock_incentives_api_with( [ $incentive_data ] );

		// Act.
		$badge = $this->incentives_service->onboarding_task_badge( '' );

		// Assert.
		$this->assertSame( 'task_badge', $badge );
	}

	public function test_get_connect_incentive_non_supported_country() {
		// Arrange.
		$this->mock_incentives_api_with( [ $this->mock_incentive_data ] );
		add_filter(
			'woocommerce_countries_base_country',
			function () {
				return '__';
			}
		);

		// Act and assert.
		$this->assertNull( $this->incentives_service->get_connect_incentive() );
		$this->assertSame( 0, $this->incentives_api_requests );

		// Clean up.
		remove_all_filters( 'woocommerce_countries_base_country' );
	}

	public function test_get_connect_incentive_returns_connect_page_incentive() {
		// Arrange.
		$other_incentive         = $this->mock_incentive_data;
		$other_incentive['id']   = 'other_incentive_id';
		$other_incentive['type'] = 'welcome_page';
		$this->mock_incentives_api_with( [ $other_incentive, $this->mock_incentive_data ] );

		// Act.
		$incentive = $this->incentives_service->get_connect_incentive();

		// Assert.
		$this->assertSame( 'incentive_id', $incentive['id'] ?? null );
	}

	public function test_get_connect_incentive_skips_incentive_missing_display_details() {
		// Arrange.
		$incentive_data = $this->mock_incentive_data;
		unset( $incentive_data['tc_url'] );
		$this->mock_incentives_api_with( [ $incentive_data ] );

		// Act and assert.
		$this->assertNull( $this->incentives_service->get_connect_incentive() );
	}

	public function test_get_connect_incentive_without_core_provider() {
		// Arrange.
		$this->mock_incentives_api_with( [ $this->mock_incentive_data ] );
		$incentives_service = $this->getMockBuilder( WC_Payments_Incentives_Service::class )
			->onlyMethods( [ 'get_core_incentives_provider' ] )
			->getMock();
		$incentives_service->method( 'get_core_incentives_provider' )->willReturn( null );

		// Act and assert.
		$this->assertNull( $incentives_service->get_connect_incentive() );
		$this->assertSame( 0, $this->incentives_api_requests );
	}

	/**
	 * Stubs the incentives API to return the given incentives list, without caching it.
	 *
	 * @param array $incentives The incentives list to return.
	 *
	 * @return void
	 */
	private function mock_incentives_api_with( array $incentives = [] ) {
		add_filter(
			'pre_http_request',
			function ( $value, $parsed_args, $url ) use ( $incentives ) {
				if ( ! str_starts_with( $url, 'https://public-api.wordpress.com/wpcom/v2/wcpay/incentives' ) ) {
					return $value;
				}

				++$this->incentives_api_requests;

				return [
					'response' => [ 'code' => 200 ],
					'body'     => wp_json_encode( $incentives ),
					'headers'  => [
						'cache-for' => '0',
					],
				];
			},
			10,
			3
		);
	}
}
