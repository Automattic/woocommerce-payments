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

	public function test_assignment_key_returns_the_stored_tracks_anon_id() {
		$this->mock_legacy_proxy
			->method( 'call_function' )
			->willReturnMap(
				[
					[ 'get_current_user_id', 7 ],
					[ 'get_user_meta', 7, 'jetpack_tracks_anon_id', true, 'woo:cJ8kL2mN' ],
				]
			);
		$this->mock_legacy_proxy
			->expects( $this->never() )
			->method( 'call_static' );

		$this->assertSame(
			'woo:cJ8kL2mN',
			$this->invoke_assignment_key(),
			'ExPlat must be keyed on the same anon-ID that Tracks stamps on the prompt events.'
		);
	}

	public function test_assignment_key_generates_and_persists_a_missing_anon_id() {
		$this->mock_legacy_proxy
			->method( 'call_function' )
			->willReturnMap(
				[
					[ 'get_current_user_id', 7 ],
					[ 'get_user_meta', 7, 'jetpack_tracks_anon_id', true, '' ],
					[ 'class_exists', '\Jetpack_Tracks_Client', true ],
					[ 'update_user_meta', 7, 'jetpack_tracks_anon_id', 'woo:pQ4rS6tU', true ],
				]
			);
		$this->mock_legacy_proxy
			->expects( $this->once() )
			->method( 'call_static' )
			->with( '\Jetpack_Tracks_Client', 'get_anon_id' )
			->willReturn( 'woo:pQ4rS6tU' );

		$this->assertSame( 'woo:pQ4rS6tU', $this->invoke_assignment_key() );
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
			'no current user'               => [ 0, '', true, '' ],
			'Jetpack_Tracks_Client missing' => [ 7, '', false, '' ],
			'anon-ID cannot be generated'   => [ 7, '', true, '' ],
			'non-string anon-ID'            => [ 7, '', true, false ],
		];
	}

	/**
	 * @dataProvider provider_invalid_assignment_keys
	 *
	 * @param int    $user_id          Current user ID.
	 * @param string $stored_anon_id   Anon-ID held in user meta.
	 * @param bool   $tracks_client    Whether Jetpack_Tracks_Client exists.
	 * @param mixed  $generated_anon_id Anon-ID returned by Jetpack_Tracks_Client.
	 */
	public function test_get_variant_returns_control_when_assignment_key_unavailable( int $user_id, string $stored_anon_id, bool $tracks_client, $generated_anon_id ) {
		$this->mock_legacy_proxy
			->method( 'call_function' )
			->willReturnMap(
				[
					[ 'get_current_user_id', $user_id ],
					[ 'get_user_meta', $user_id, 'jetpack_tracks_anon_id', true, $stored_anon_id ],
					[ 'class_exists', '\Jetpack_Tracks_Client', $tracks_client ],
					[ 'get_option', 'woocommerce_allow_tracking', 'yes' ],
				]
			);
		$this->mock_legacy_proxy
			->method( 'call_static' )
			->willReturn( $generated_anon_id );

		$this->assertSame( Experiment::VARIANT_CONTROL, $this->sut->get_variant() );
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
