<?php
/**
 * Class ReviewPromptExperimentTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Experiment;

use WCPAY_UnitTestCase;
use WCPay\Internal\Experiment\Experiment;
use WCPay\Internal\Experiment\ReviewPromptExperiment;
use WCPay\Internal\Proxy\LegacyProxy;

/**
 * Tests for ReviewPromptExperiment.
 */
class ReviewPromptExperimentTest extends WCPAY_UnitTestCase {
	/**
	 * Mocked LegacyProxy.
	 *
	 * @var LegacyProxy|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $mock_legacy_proxy;

	/**
	 * System under test.
	 *
	 * @var ReviewPromptExperiment
	 */
	private $sut;

	public function set_up() {
		parent::set_up();
		$this->mock_legacy_proxy = $this->createMock( LegacyProxy::class );
		$this->sut               = new ReviewPromptExperiment( $this->mock_legacy_proxy );
	}

	public function test_name_returns_experiment_slug() {
		$this->assertSame( 'woopayments_review_prompt_design_v1', $this->sut->name() );
	}

	public function test_get_variant_returns_control_when_jetpack_options_missing() {
		$this->mock_legacy_proxy
			->method( 'call_function' )
			->willReturnMap(
				[
					[ 'class_exists', '\Jetpack_Options', false ],
					[ 'get_option', 'woocommerce_allow_tracking', 'yes' ],
				]
			);

		$this->assertSame( Experiment::VARIANT_CONTROL, $this->sut->get_variant() );
	}

	public function test_get_variant_returns_control_when_blog_id_empty() {
		$this->mock_legacy_proxy
			->method( 'call_function' )
			->willReturnMap(
				[
					[ 'class_exists', '\Jetpack_Options', true ],
					[ 'get_option', 'woocommerce_allow_tracking', 'yes' ],
				]
			);
		$this->mock_legacy_proxy
			->method( 'call_static' )
			->with( '\Jetpack_Options', 'get_option', 'id' )
			->willReturn( false );

		$this->assertSame( Experiment::VARIANT_CONTROL, $this->sut->get_variant() );
	}
}
