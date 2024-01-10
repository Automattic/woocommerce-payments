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
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		$this->mock_api_client       = $this->createMock( WC_Payments_API_Client::class );
		$this->compatibility_service = new Compatibility_Service( $this->mock_api_client );
		$this->compatibility_service->init_hooks();
	}

	public function test_registers_woocommerce_filters_properly() {
		$priority = has_filter( 'woocommerce_payments_account_refreshed', [ $this->compatibility_service, 'update_compatibility_data' ] );
		$this->assertEquals( 10, $priority );
		$priority = has_action( 'after_switch_theme', [ $this->compatibility_service, 'update_compatibility_data' ] );
		$this->assertEquals( 10, $priority );
	}

	public function test_update_compatibility_data() {
		$stylesheet = 'my_theme_name';
		add_filter(
			'stylesheet',
			function( $theme ) use ( $stylesheet ) {
				return $stylesheet;
			}
		);

		// Arrange: Create the expected value to be passed to update_compatibility_data.
		$expected = [
			'woopayments_version' => WCPAY_VERSION_NUMBER,
			'woocommerce_version' => WC_VERSION,
			'blog_theme'          => $stylesheet,
			'post_types_count'    => [
				'post'       => 1,
				'page'       => 6,
				'attachment' => 0,
				'product'    => 12,
			],
		];

		// Arrange: Insert test posts.
		$post_ids = $this->insert_test_posts( $expected['post_types_count'] );

		// Arrange/Assert: Set the expectations for update_compatibility_data.
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'update_compatibility_data' )
			->with( $expected );

		// Act: Call the method we're testing.
		$this->compatibility_service->update_compatibility_data();

		// Clean up: Delete the test posts.
		$this->delete_test_posts( $post_ids );
	}

	/**
	 * Insert test posts for use during a unit test.
	 *
	 * @param  array $post_types  Assoc array of post types as keys and the number of posts to create for each.
	 *
	 * @return array Array of post IDs that were created.
	 */
	private function insert_test_posts( array $post_types ): array {
		$post_ids = [];
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

		return $post_ids;
	}

	/**
	 * Delete test posts that were created during a unit test.
	 *
	 * @param array $post_ids Array of post IDs to delete.
	 */
	private function delete_test_posts( array $post_ids ) {
		foreach ( $post_ids as $post_id ) {
			wp_delete_post( (int) $post_id, true );
		}
	}
}
