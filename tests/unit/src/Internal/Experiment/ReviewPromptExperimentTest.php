<?php
/**
 * Class ReviewPromptExperimentTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Experiment;

use WCPAY_UnitTestCase;
use WCPay\Experimental_Abtest;
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

	public function test_assignment_key_derives_from_blog_id() {
		$this->mock_legacy_proxy
			->method( 'call_function' )
			->with( 'class_exists', '\Jetpack_Options' )
			->willReturn( true );
		$this->mock_legacy_proxy
			->method( 'call_static' )
			->with( '\Jetpack_Options', 'get_option', 'id' )
			->willReturn( 123456 );

		// The key format is a live experiment contract: changing it
		// mid-experiment re-randomizes every store's assignment.
		$method = new \ReflectionMethod( ReviewPromptExperiment::class, 'assignment_key' );
		$method->setAccessible( true );

		$this->assertSame( 'woopayments_store_123456', $method->invoke( $this->sut ) );
	}

	public function test_variants_match_the_explat_registration() {
		// The variant strings are a live experiment contract: renaming an arm
		// mid-experiment re-randomizes assignment, so pin them as literals.
		$method = new \ReflectionMethod( ReviewPromptExperiment::class, 'variants' );
		$method->setAccessible( true );

		$this->assertSame(
			[ 'control', 'treatment_illustration', 'treatment_revised' ],
			$method->invoke( $this->sut )
		);
	}

	public function test_create_abtest_builds_an_explat_client() {
		// ReviewPromptExperiment relies on the base class's default factory
		// rather than overriding it, so confirm the concrete class yields a
		// real ExPlat client (the base test double stubs this seam out).
		$method = new \ReflectionMethod( ReviewPromptExperiment::class, 'create_abtest' );
		$method->setAccessible( true );

		$this->assertInstanceOf(
			Experimental_Abtest::class,
			$method->invoke( $this->sut, 'woopayments_store_123456' )
		);
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
