<?php
/**
 * Class ExperimentTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Experiment;

use WCPAY_UnitTestCase;
use WCPay\Experimental_Abtest;
use WCPay\Internal\Experiment\Experiment;
use WCPay\Internal\Proxy\LegacyProxy;

/**
 * Tests for the abstract Experiment base class via a concrete test double.
 */
class ExperimentTest extends WCPAY_UnitTestCase {
	/**
	 * Mocked LegacyProxy.
	 *
	 * @var LegacyProxy|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $mock_legacy_proxy;

	public function set_up() {
		parent::set_up();
		$this->mock_legacy_proxy = $this->createMock( LegacyProxy::class );
	}

	/**
	 * Build an experiment test double with a fixed ExPlat response.
	 *
	 * @param mixed       $abtest_variation  What the stubbed abtest returns (string|null|array).
	 * @param string      $assignment_key    What assignment_key() returns.
	 * @param string|null &$captured_anon_id Set to the anon_id passed to create_abtest.
	 * @param int|null    &$abtest_calls     Incremented each time create_abtest is called.
	 * @return Experiment
	 */
	private function build_experiment( $abtest_variation, string $assignment_key, &$captured_anon_id = null, &$abtest_calls = null ) {
		$abtest_calls = 0;
		$mock_abtest  = $this->createMock( Experimental_Abtest::class );
		$mock_abtest->method( 'get_variation' )->with( 'test_experiment' )->willReturn( $abtest_variation );

		return new class( $this->mock_legacy_proxy, $mock_abtest, $assignment_key, $captured_anon_id, $abtest_calls ) extends Experiment {
			/**
			 * Number of times assignment_key() has been resolved.
			 *
			 * @var int
			 */
			public $assignment_key_calls = 0;

			/**
			 * Stubbed abtest client.
			 *
			 * @var Experimental_Abtest
			 */
			private $abtest;

			/**
			 * Assignment key.
			 *
			 * @var string
			 */
			private $key;

			/**
			 * Captured anon_id.
			 *
			 * @var string|null
			 */
			private $captured;

			/**
			 * Abtest factory call count.
			 *
			 * @var int
			 */
			private $calls;

			public function __construct( $legacy_proxy, $abtest, $key, &$captured, &$calls ) {
				parent::__construct( $legacy_proxy );
				$this->abtest   = $abtest;
				$this->key      = $key;
				$this->captured = &$captured;
				$this->calls    = &$calls;
			}

			public function name(): string {
				return 'test_experiment';
			}

			protected function assignment_key(): string {
				++$this->assignment_key_calls;
				return $this->key;
			}

			protected function variants(): array {
				return [ self::VARIANT_CONTROL, 'treatment_a' ];
			}

			protected function create_abtest( string $anon_id ): Experimental_Abtest {
				$this->captured = $anon_id;
				++$this->calls;
				return $this->abtest;
			}
		};
	}

	private function set_consent( bool $granted ) {
		$this->mock_legacy_proxy
			->method( 'call_function' )
			->willReturnMap( [ [ 'class_exists', '\WC_Site_Tracking', true ] ] );
		$this->mock_legacy_proxy
			->method( 'call_static' )
			->willReturnMap( [ [ '\WC_Site_Tracking', 'is_tracking_enabled', $granted ] ] );
	}

	/**
	 * Data provider for test_returns_control_for_fallback_cases.
	 *
	 * @return array
	 */
	public function provider_control_fallback_cases() {
		return [
			'without consent'      => [ false, 'store_123', 'treatment_a', null ],
			'empty assignment key' => [ true, '', 'treatment_a', null ],
			'unknown variant'      => [ true, 'store_123', 'renamed_arm_typo', 'store_123' ],
			'non-string variation' => [ true, 'store_123', null, 'store_123' ],
		];
	}

	/**
	 * @dataProvider provider_control_fallback_cases
	 *
	 * @param bool        $has_consent       Whether tracking consent is granted.
	 * @param string      $assignment_key    The assignment key returned by the experiment.
	 * @param mixed       $abtest_variation  The value returned by ExPlat.
	 * @param string|null $expected_anon_id  Expected anon_id passed to ExPlat; null when no call is expected.
	 */
	public function test_returns_control_for_fallback_cases( bool $has_consent, string $assignment_key, $abtest_variation, ?string $expected_anon_id ) {
		$this->set_consent( $has_consent );
		$captured   = null;
		$experiment = $this->build_experiment( $abtest_variation, $assignment_key, $captured );

		$this->assertSame( 'control', $experiment->get_variant() );
		$this->assertSame( $expected_anon_id, $captured );
	}

	public function test_returns_assigned_variant_and_passes_assignment_key() {
		$this->set_consent( true );
		$captured   = null;
		$experiment = $this->build_experiment( 'treatment_a', 'store_123', $captured );

		$this->assertSame( 'treatment_a', $experiment->get_variant() );
		$this->assertSame( 'store_123', $captured );
	}

	public function test_consent_is_checked_before_the_assignment_key_is_resolved() {
		$this->set_consent( false );
		$experiment = $this->build_experiment( 'treatment_a', 'store_123' );

		$this->assertSame( 'control', $experiment->get_variant() );
		$this->assertSame(
			0,
			$experiment->assignment_key_calls,
			'assignment_key() can persist identity state, so it must not run without consent.'
		);
	}

	public function test_consent_uses_the_event_gating_predicate_not_the_raw_option() {
		$this->mock_legacy_proxy
			->method( 'call_function' )
			->willReturnMap(
				[
					[ 'class_exists', '\WC_Site_Tracking', true ],
					[ 'get_option', 'woocommerce_allow_tracking', 'yes' ],
				]
			);
		$this->mock_legacy_proxy
			->method( 'call_static' )
			->willReturnMap( [ [ '\WC_Site_Tracking', 'is_tracking_enabled', false ] ] );

		$experiment = $this->build_experiment( 'treatment_a', 'store_123' );

		$this->assertSame(
			'control',
			$experiment->get_variant(),
			'A site can filter tracking off while the option stays yes; assignment must follow the events.'
		);
	}

	public function test_consent_falls_back_to_the_option_when_wc_site_tracking_is_missing() {
		$this->mock_legacy_proxy
			->method( 'call_function' )
			->willReturnMap(
				[
					[ 'class_exists', '\WC_Site_Tracking', false ],
					[ 'get_option', 'woocommerce_allow_tracking', 'yes' ],
				]
			);

		$experiment = $this->build_experiment( 'treatment_a', 'store_123' );

		$this->assertSame( 'treatment_a', $experiment->get_variant() );
	}

	public function test_validates_variants() {
		$experiment = $this->build_experiment( 'treatment_a', 'store_123' );

		$this->assertTrue( $experiment->is_valid_variant( 'control' ) );
		$this->assertTrue( $experiment->is_valid_variant( 'treatment_a' ) );
		$this->assertFalse( $experiment->is_valid_variant( 'unknown_arm' ) );
	}

	public function test_memoizes_variant_per_instance() {
		$this->set_consent( true );
		$captured   = null;
		$calls      = null;
		$experiment = $this->build_experiment( 'treatment_a', 'store_123', $captured, $calls );

		$this->assertSame( 'treatment_a', $experiment->get_variant() );
		$this->assertSame( 'treatment_a', $experiment->get_variant() );
		$this->assertSame( 1, $calls, 'repeated get_variant() calls must not re-hit ExPlat' );
	}
}
