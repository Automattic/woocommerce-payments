<?php
/**
 * Class WCPay_Multi_Currency_Caching_Environment_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\MultiCurrency\CachingEnvironment;

/**
 * CachingEnvironment unit tests.
 */
class WCPay_Multi_Currency_Caching_Environment_Tests extends WCPAY_UnitTestCase {

	/**
	 * Builds a CachingEnvironment with its detection seams stubbed.
	 *
	 * @param bool        $has_dropin Whether the page-cache drop-in is present.
	 * @param string|null $plugin     Active cache plugin slug, or null.
	 * @param string|null $host       Managed host slug, or null.
	 *
	 * @return CachingEnvironment|PHPUnit\Framework\MockObject\MockObject
	 */
	private function get_detector_with_seams( bool $has_dropin, ?string $plugin, ?string $host ) {
		$detector = $this->getMockBuilder( CachingEnvironment::class )
			->onlyMethods( [ 'has_page_cache_dropin', 'get_active_cache_plugin', 'get_managed_host' ] )
			->getMock();
		$detector->method( 'has_page_cache_dropin' )->willReturn( $has_dropin );
		$detector->method( 'get_active_cache_plugin' )->willReturn( $plugin );
		$detector->method( 'get_managed_host' )->willReturn( $host );

		return $detector;
	}

	public function test_detects_page_cache_dropin() {
		$detector = $this->get_detector_with_seams( true, null, null );

		$this->assertSame( 'advanced_cache_dropin', $detector->get_detected_provider() );
		$this->assertTrue( $detector->is_page_caching_active() );
	}

	public function test_detects_active_cache_plugin() {
		$detector = $this->get_detector_with_seams( false, 'wp_rocket', null );

		$this->assertSame( 'wp_rocket', $detector->get_detected_provider() );
		$this->assertTrue( $detector->is_page_caching_active() );
	}

	public function test_detects_managed_host() {
		$detector = $this->get_detector_with_seams( false, null, 'pressable' );

		$this->assertSame( 'pressable', $detector->get_detected_provider() );
		$this->assertTrue( $detector->is_page_caching_active() );
	}

	public function test_returns_null_when_no_signal_present() {
		$detector = $this->get_detector_with_seams( false, null, null );

		$this->assertNull( $detector->get_detected_provider() );
		$this->assertFalse( $detector->is_page_caching_active() );
	}

	public function test_dropin_takes_precedence_over_other_signals() {
		$detector = $this->get_detector_with_seams( true, 'wp_rocket', 'pressable' );

		$this->assertSame( 'advanced_cache_dropin', $detector->get_detected_provider() );
	}

	public function test_filter_can_force_caching_active() {
		$detector = $this->get_detector_with_seams( false, null, null );

		add_filter( 'wcpay_multi_currency_page_caching_active', '__return_true' );
		$this->assertTrue( $detector->is_page_caching_active() );
		remove_filter( 'wcpay_multi_currency_page_caching_active', '__return_true' );
	}

	public function test_filter_can_force_caching_inactive() {
		$detector = $this->get_detector_with_seams( true, null, null );

		add_filter( 'wcpay_multi_currency_page_caching_active', '__return_false' );
		$this->assertFalse( $detector->is_page_caching_active() );
		remove_filter( 'wcpay_multi_currency_page_caching_active', '__return_false' );
	}
}
