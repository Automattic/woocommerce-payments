<?php
/**
 * Class Compatibility_Service_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Compatibility_Service;

/**
 * Unit tests related to the Compatibility_Service class.
 */
class Compatibility_Service_Test extends WCPAY_UnitTestCase {
	/**
	 * Mock WC_Payments_API_Client.
	 *
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $mock_api_client;

	/**
	 * Compatibility_Service.
	 *
	 * @var Compatibility_Service
	 */
	private $compatibility_service;

	/**
	 * Test theme name.
	 *
	 * @var string
	 */
	private $stylesheet = 'my_theme_name';

	/**
	 * Test active plugins.
	 *
	 * @var array
	 */
	private $active_plugins = [
		'woocommerce/woocommerce.php',
		'woocommerce-payments/woocommerce-payments.php',
	];

	/**
	 * Test post types count.
	 *
	 * @var array
	 */
	private $post_types_count = [
		'post'       => 1,
		'page'       => 6,
		'attachment' => 0,
		'product'    => 12,
	];

	/**
	 * Test posts.
	 *
	 * @var array
	 */
	private $test_posts = [];

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		$this->mock_api_client       = $this->createMock( WC_Payments_API_Client::class );
		$this->compatibility_service = new Compatibility_Service( $this->mock_api_client );
		$this->compatibility_service->init_hooks();

		$this->add_stylesheet_filter();
		$this->add_option_active_plugins_filter();
		$this->insert_test_posts();
	}

	/**
	 * Post-test anarchy
	 */
	public function tear_down() {
		parent::tear_down();

		$this->remove_stylesheet_filters();
		$this->remove_option_active_plugins_filters();
		$this->delete_test_posts();
	}

	/**
	 * Tests to make sure filters are registered correctly.
	 *
	 * @param string $filter            The filter name.
	 * @param string $method            The method being called in the class.
	 *
	 * @dataProvider provider_test_registers_woocommerce_filters_properly
	 *
	 * @return void
	 */
	public function test_registers_woocommerce_filters_properly( string $filter, string $method ) {
		$priority = has_filter( $filter, [ $this->compatibility_service, $method ] );
		$this->assertEquals( 10, $priority );
	}

	public function provider_test_registers_woocommerce_filters_properly(): array {
		return [
			'woocommerce_payments_account_refreshed' => [
				'filter' => 'woocommerce_payments_account_refreshed',
				'method' => 'update_compatibility_data',
			],
			'after_switch_theme'                     => [
				'filter' => 'woocommerce_payments_account_refreshed',
				'method' => 'update_compatibility_data',
			],
			'wc_payments_get_onboarding_data_args'   => [
				'filter' => 'wc_payments_get_onboarding_data_args',
				'method' => 'add_compatibility_onboarding_data',
			],
		];
	}

	public function test_update_compatibility_data() {
		// Arrange: Create the expected value to be passed to update_compatibility_data.
		$expected = $this->get_mock_compatibility_data();

		// Arrange/Assert: Set the expectations for update_compatibility_data.
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'update_compatibility_data' )
			->with( $expected );

		// Act: Call the method we're testing.
		$this->compatibility_service->update_compatibility_data();
	}

	public function test_update_compatibility_data_active_plugins_false() {
		// Arrange: Create the expected value to be passed to update_compatibility_data.
		$expected = $this->get_mock_compatibility_data(
			[
				'active_plugins' => [],
			]
		);

		// Arrange: Purposely break/delete the active_plugins option in WP.
		$this->break_active_plugins_option();

		// Arrange/Assert: Set the expectations for update_compatibility_data.
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'update_compatibility_data' )
			->with( $expected );

		// Act: Call the method we're testing.
		$this->compatibility_service->update_compatibility_data();

		// Arrange: Fix the broke active_plugins option in WP.
		$this->fix_active_plugins_option();
	}

	public function test_add_compatibility_onboarding_data() {
		// Arrange: Create the expected value.
		$expected = [ 'compatibility_data' => $this->get_mock_compatibility_data() ];

		// Act/Assert: Call the method we're testing and confirm we get the expected value.
		$this->assertSame( $expected, $this->compatibility_service->add_compatibility_onboarding_data( [] ) );
	}

	/**
	 * Returns the mock compatibility data.
	 *
	 * @param array $args If any values need to be overridden, the values can be added here.
	 *
	 * @return array
	 */
	private function get_mock_compatibility_data( array $args = [] ): array {
		return array_merge(
			[
				'woopayments_version' => WCPAY_VERSION_NUMBER,
				'woocommerce_version' => WC_VERSION,
				'blog_theme'          => $this->stylesheet,
				'active_plugins'      => $this->active_plugins,
				'post_types_count'    => $this->post_types_count,
			],
			$args
		);
	}

	/**
	 * Adds a filter for the theme/stylesheet name.
	 * Will use the default defined in the test class if no params passed.
	 *
	 * @param string $stylesheet The theme name you'd like to use, default null.
	 *
	 * @return void
	 */
	private function add_stylesheet_filter( $stylesheet = null ): void {
		$stylesheet = $stylesheet ?? $this->stylesheet;
		add_filter(
			'stylesheet',
			function ( $theme ) use ( $stylesheet ) {
				return $stylesheet;
			},
			404 // 404 is used to be able to use remove_all_filters later.
		);
	}

	// Removes all stylesheet/theme name filters.
	private function remove_stylesheet_filters(): void {
		remove_all_filters( 'stylesheet', 404 );
	}

	/**
	 * Adds a filter for the active plugins array.
	 * Will use the default defined in the test class if no params passed.
	 *
	 * @param array $plugins The plugin array you'd like to use, default null.
	 *
	 * @return void
	 */
	private function add_option_active_plugins_filter( $plugins = null ): void {
		$plugins = $plugins ?? $this->active_plugins;
		add_filter(
			'option_active_plugins',
			function ( $active_plugins ) use ( $plugins ) {
				return $plugins;
			},
			404 // 404 is used to be able to use remove_all_filters later.
		);
	}

	// Removes all active plugin filters.
	private function remove_option_active_plugins_filters() {
		remove_all_filters( 'option_active_plugins', [ $this, 'active_plugins_filter_return' ], 404 );
	}

	// Used to purposely delete the active_plugins option in WP.
	private function break_active_plugins_option() {
		update_option( 'temp_active_plugins', get_option( 'active_plugins' ) );
		delete_option( 'active_plugins' );
	}

	// Used to restore the active_plugins option in WP after break_active_plugins_option is used.
	private function fix_active_plugins_option() {
		update_option( 'active_plugins', get_option( 'temp_active_plugins' ) );
		delete_option( 'temp_active_plugins' );
	}

	/**
	 * Insert test posts for use during a unit test.
	 * Will use the default defined in the test class if no params passed.
	 *
	 * @param  array $post_types  Assoc array of post types as keys and the number of posts to create for each.
	 *
	 * @return array Array of post IDs that were created.
	 */
	private function insert_test_posts( array $post_types = [] ): array {
		$post_types = ! empty( $post_types ) ? $post_types : $this->post_types_count;
		$post_ids   = [];
		foreach ( $post_types as $post_type => $count ) {
			$title_content = 'This is a ' . $post_type . ' test post';
			for ( $i = 0; $i < $count; $i++ ) {
				$post_ids[] = (int) wp_insert_post(
					[
						'post_title'   => $title_content,
						'post_content' => $title_content,
						'post_type'    => $post_type,
						'post_status'  => 'publish',
					]
				);
			}
		}

		$this->test_posts = $post_ids;
		return $post_ids;
	}

	/**
	 * Delete test posts that were created during a unit test.
	 *
	 * @param array $post_ids Array of post IDs to delete.
	 */
	private function delete_test_posts( array $post_ids = [] ) {
		$post_ids = ! empty( $post_ids ) ? $post_ids : $this->test_posts;
		foreach ( $post_ids as $post_id ) {
			wp_delete_post( (int) $post_id, true );
		}
	}
}
