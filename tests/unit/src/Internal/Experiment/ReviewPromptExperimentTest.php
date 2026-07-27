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

	public function test_assignment_key_defers_to_the_helper_that_stamps_tracks_events() {
		$this->stub_call_function();
		$this->mock_legacy_proxy
			->expects( $this->once() )
			->method( 'call_static' )
			->with( '\WC_Tracks_Client', 'get_identity', 7 )
			->willReturn(
				[
					'_ut' => 'anon',
					'_ui' => 'woo:cJ8kL2mN',
				]
			);

		$this->assertSame(
			'woo:cJ8kL2mN',
			$this->invoke_assignment_key(),
			'ExPlat must be keyed on the anon-ID Tracks stamps on the events.'
		);
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
			$method->invoke( $this->sut, 'woo:cJ8kL2mN' )
		);
	}

	/**
	 * Data provider for test_get_variant_returns_control_when_assignment_key_unavailable.
	 *
	 * @return array
	 */
	public function provider_invalid_assignment_keys() {
		return [
			'no current user'          => [
				0,
				true,
				[
					'_ut' => 'anon',
					'_ui' => 'woo:cJ8kL2mN',
				],
			],
			'WC_Tracks_Client missing' => [
				7,
				false,
				[
					'_ut' => 'anon',
					'_ui' => 'woo:cJ8kL2mN',
				],
			],
			'wpcom identity'           => [
				7,
				true,
				[
					'_ut' => 'wpcom:user_id',
					'_ui' => '12345',
				],
			],
			'malformed identity'       => [ 7, true, false ],
			'non-string anon-ID'       => [
				7,
				true,
				[
					'_ut' => 'anon',
					'_ui' => 12345,
				],
			],
		];
	}

	/**
	 * @dataProvider provider_invalid_assignment_keys
	 *
	 * @param int   $user_id       Current user ID.
	 * @param bool  $tracks_client Whether WC_Tracks_Client exists.
	 * @param mixed $identity      Identity returned by WC_Tracks_Client::get_identity().
	 */
	public function test_get_variant_returns_control_when_assignment_key_unavailable( int $user_id, bool $tracks_client, $identity ) {
		$this->stub_call_function( $user_id, $tracks_client );
		$this->mock_legacy_proxy
			->method( 'call_static' )
			->willReturn( $identity );

		$this->assertSame( Experiment::VARIANT_CONTROL, $this->sut->get_variant() );
	}

	public function test_a_wpcom_identity_sits_the_experiment_out() {
		$this->stub_call_function();
		$this->mock_legacy_proxy
			->method( 'call_static' )
			->willReturn(
				[
					'_ut' => 'wpcom:user_id',
					'_ui' => '12345',
				]
			);

		$this->assertSame(
			'',
			$this->invoke_assignment_key(),
			'A wpcom identity has no anon-ID for ExPlat to key on.'
		);
	}

	/**
	 * Stub the LegacyProxy function calls made while resolving a variant.
	 *
	 * @param int  $user_id       Current user ID.
	 * @param bool $tracks_client Whether WC_Tracks_Client exists.
	 */
	private function stub_call_function( int $user_id = 7, bool $tracks_client = true ) {
		$this->mock_legacy_proxy
			->method( 'call_function' )
			->willReturnMap(
				[
					[ 'get_current_user_id', $user_id ],
					[ 'class_exists', '\WC_Tracks_Client', $tracks_client ],
					[ 'get_option', 'woocommerce_allow_tracking', 'yes' ],
				]
			);
	}

	/**
	 * Invoke the protected assignment_key() on the system under test.
	 *
	 * @return string
	 */
	private function invoke_assignment_key(): string {
		$method = new \ReflectionMethod( ReviewPromptExperiment::class, 'assignment_key' );
		$method->setAccessible( true );

		return $method->invoke( $this->sut );
	}
}
