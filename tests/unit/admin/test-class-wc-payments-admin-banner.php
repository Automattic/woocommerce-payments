<?php
/**
 * Class WC_Payments_Admin_Banner_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WC_Payments_Admin_Banner coordinator unit tests.
 *
 * The class itself owns no banner-specific logic; per-banner behaviour is
 * tested against each WC_Payments_Abstract_Admin_Banner subclass directly.
 * This file just locks in the coordinator contract: it dispatches
 * init_hooks() / init_global_hooks() to every registered banner.
 */
class WC_Payments_Admin_Banner_Test extends WCPAY_UnitTestCase {

	/**
	 * Coordinators built during the test. tear_down() iterates each one and
	 * removes every callback init_hooks() / init_global_hooks() can register
	 * for its banners — not just the ones the test happened to assert on —
	 * so untracked hooks (handle_cta, register_script, snooze_notice, etc.)
	 * don't leak into the next test and cause order-dependent failures.
	 *
	 * @var WC_Payments_Admin_Banner[]
	 */
	private $coordinators = [];

	public function tear_down(): void {
		foreach ( $this->coordinators as $coordinator ) {
			foreach ( $this->get_coordinator_banners( $coordinator ) as $banner ) {
				$this->remove_banner_hooks( $banner );
			}
		}
		$this->coordinators = [];
		parent::tear_down();
	}

	public function test_init_global_hooks_dispatches_to_each_banner(): void {
		$coordinator = $this->make_coordinator();
		$banners     = $this->get_coordinator_banners( $coordinator );

		$coordinator->init_global_hooks();

		// One-and-done registers three order-completion invalidators.
		$one_and_done = $this->find_banner( $banners, WC_Payments_One_And_Done_Banner::class );
		foreach ( [ 'woocommerce_payment_complete', 'woocommerce_order_status_completed', 'woocommerce_order_status_processing' ] as $tag ) {
			$this->assert_action_registered( $tag, [ $one_and_done, 'invalidate_cache_on_order' ] );
		}

		// Post-KYC registers the first-live-sale invalidator.
		$post_kyc = $this->find_banner( $banners, WC_Payments_Post_Kyc_Activation_Banner::class );
		$this->assert_action_registered(
			'add_option_' . WC_Payments_Order_Service::HAS_LIVE_SALE_OPTION,
			[ $post_kyc, 'invalidate_cache' ]
		);
	}

	public function test_init_hooks_dispatches_to_each_banner(): void {
		$coordinator = $this->make_coordinator();
		$banners     = $this->get_coordinator_banners( $coordinator );

		$coordinator->init_hooks();

		// Every banner registers hide_notice on admin_init via the base class.
		foreach ( $banners as $banner ) {
			$this->assert_action_registered( 'admin_init', [ $banner, 'hide_notice' ] );
		}

		// Post-KYC additionally registers the account_refreshed invalidator.
		$post_kyc = $this->find_banner( $banners, WC_Payments_Post_Kyc_Activation_Banner::class );
		$this->assert_action_registered( 'woocommerce_payments_account_refreshed', [ $post_kyc, 'invalidate_cache' ] );
	}

	/**
	 * Asserts a specific callback is registered on a hook. Uses the specific
	 * `[$object, 'method']` callable form rather than `has_action( $tag )`,
	 * which would return any callback registered on the tag (false positives
	 * when WC core or other plugins share the hook).
	 *
	 * @param string   $tag      Hook tag.
	 * @param callable $callback Specific callback to assert is registered.
	 * @return void
	 */
	private function assert_action_registered( string $tag, callable $callback ): void {
		$this->assertNotFalse(
			has_action( $tag, $callback ),
			"Expected callback to be registered on hook '$tag'."
		);
	}

	/**
	 * Removes every callback init_hooks() / init_global_hooks() can register
	 * for a banner. `remove_action()` is idempotent for unregistered callbacks,
	 * so this is safe to call regardless of which init_* the test exercised.
	 *
	 * Kept in sync by hand with the production hook registrations in
	 * WC_Payments_Abstract_Admin_Banner::init_hooks() and the per-subclass
	 * overrides.
	 *
	 * @param WC_Payments_Abstract_Admin_Banner $banner Banner instance.
	 * @return void
	 */
	private function remove_banner_hooks( WC_Payments_Abstract_Admin_Banner $banner ): void {
		// Base init_hooks() registrations.
		remove_action( 'admin_init', [ $banner, 'hide_notice' ] );
		remove_action( 'admin_init', [ $banner, 'snooze_notice' ] );
		remove_action( 'admin_init', [ $banner, 'handle_cta' ] );
		remove_action( 'admin_enqueue_scripts', [ $banner, 'register_script' ], 9 );
		remove_action( 'admin_enqueue_scripts', [ $banner, 'enqueue_script' ] );

		// Subclass-specific registrations.
		if ( $banner instanceof WC_Payments_One_And_Done_Banner ) {
			remove_action( 'woocommerce_payment_complete', [ $banner, 'invalidate_cache_on_order' ] );
			remove_action( 'woocommerce_order_status_completed', [ $banner, 'invalidate_cache_on_order' ] );
			remove_action( 'woocommerce_order_status_processing', [ $banner, 'invalidate_cache_on_order' ] );
		}
		if ( $banner instanceof WC_Payments_Post_Kyc_Activation_Banner ) {
			remove_action( 'woocommerce_payments_account_refreshed', [ $banner, 'invalidate_cache' ] );
			remove_action(
				'add_option_' . WC_Payments_Order_Service::HAS_LIVE_SALE_OPTION,
				[ $banner, 'invalidate_cache' ]
			);
		}
	}

	/**
	 * Reflects into the coordinator's private $banners array so the tests can
	 * bind their assertions to the actual instances the coordinator created
	 * (and tear_down() can remove the same callbacks afterwards).
	 *
	 * @param WC_Payments_Admin_Banner $coordinator Coordinator instance.
	 * @return WC_Payments_Abstract_Admin_Banner[]
	 */
	private function get_coordinator_banners( WC_Payments_Admin_Banner $coordinator ): array {
		$reflection = new ReflectionClass( $coordinator );
		$property   = $reflection->getProperty( 'banners' );
		$property->setAccessible( true );
		return $property->getValue( $coordinator );
	}

	/**
	 * Returns the first banner in the array that's an instance of the given
	 * class. Fails the test if none is found — that itself indicates the
	 * coordinator isn't registering the expected banner.
	 *
	 * @param WC_Payments_Abstract_Admin_Banner[] $banners Banners from the coordinator.
	 * @param string                              $class   Expected concrete class name.
	 * @return WC_Payments_Abstract_Admin_Banner
	 */
	private function find_banner( array $banners, string $class ): WC_Payments_Abstract_Admin_Banner {
		foreach ( $banners as $banner ) {
			if ( $banner instanceof $class ) {
				return $banner;
			}
		}
		$this->fail( "Coordinator does not contain a $class instance." );
	}

	private function make_coordinator(): WC_Payments_Admin_Banner {
		$mock_gateway         = $this->getMockBuilder( WC_Payment_Gateway_WCPay::class )
			->disableOriginalConstructor()
			->getMock();
		$mock_account         = $this->getMockBuilder( WC_Payments_Account::class )
			->disableOriginalConstructor()
			->getMock();
		$coordinator          = new WC_Payments_Admin_Banner( $mock_gateway, $mock_account );
		$this->coordinators[] = $coordinator;
		return $coordinator;
	}
}
