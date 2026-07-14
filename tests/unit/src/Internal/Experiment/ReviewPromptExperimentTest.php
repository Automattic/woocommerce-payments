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

		// The key format is a live experiment contract.
		$method = new \ReflectionMethod( ReviewPromptExperiment::class, 'assignment_key' );
		$method->setAccessible( true );

		$this->assertSame( 'woopayments_store_123456', $method->invoke( $this->sut ) );
	}

	public function test_variants_match_the_explat_registration() {
		// The variant strings are a live experiment contract.
		$method = new \ReflectionMethod( ReviewPromptExperiment::class, 'variants' );
		$method->setAccessible( true );

		$this->assertSame(
			[ 'control', 'treatment_illustration', 'treatment_revised' ],
			$method->invoke( $this->sut )
		);
	}

	public function test_create_abtest_builds_an_explat_client() {
		// Confirm the concrete class uses the base factory.
		$method = new \ReflectionMethod( ReviewPromptExperiment::class, 'create_abtest' );
		$method->setAccessible( true );

		$this->assertInstanceOf(
			Experimental_Abtest::class,
			$method->invoke( $this->sut, 'woopayments_store_123456' )
		);
	}

	/**
	 * Data provider for test_get_variant_returns_control_when_assignment_key_unavailable.
	 *
	 * @return array
	 */
	public function provider_invalid_assignment_keys() {
		return [
			'Jetpack_Options missing' => [ false, null ],
			'empty blog ID'           => [ true, false ],
			'non-numeric blog ID'     => [ true, 'not-a-blog-id' ],
		];
	}

	/**
	 * @dataProvider provider_invalid_assignment_keys
	 *
	 * @param bool  $jetpack_options_exists Whether Jetpack_Options exists.
	 * @param mixed $blog_id                Blog ID returned by Jetpack_Options.
	 */
	public function test_get_variant_returns_control_when_assignment_key_unavailable( bool $jetpack_options_exists, $blog_id ) {
		$this->mock_legacy_proxy
			->method( 'call_function' )
			->willReturnMap(
				[
					[ 'class_exists', '\Jetpack_Options', $jetpack_options_exists ],
					[ 'get_option', 'woocommerce_allow_tracking', 'yes' ],
				]
			);

		if ( $jetpack_options_exists ) {
			$this->mock_legacy_proxy
				->expects( $this->once() )
				->method( 'call_static' )
				->with( '\Jetpack_Options', 'get_option', 'id' )
				->willReturn( $blog_id );
		} else {
			$this->mock_legacy_proxy
				->expects( $this->never() )
				->method( 'call_static' );
		}

		$this->assertSame( Experiment::VARIANT_CONTROL, $this->sut->get_variant() );
	}
}
