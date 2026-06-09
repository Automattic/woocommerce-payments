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
	 * Build a concrete Experiment whose abtest returns a fixed variation,
	 * capturing the anon_id it was constructed with and counting how many
	 * times the abtest is built.
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
			 * Stubbed Experimental_Abtest.
			 *
			 * @var Experimental_Abtest
			 */
			private $abtest;

			/**
			 * Assignment key to return from assignment_key().
			 *
			 * @var string
			 */
			private $key;

			/**
			 * Reference to the caller's capture variable.
			 *
			 * @var string|null
			 */
			private $captured;

			/**
			 * Reference to the caller's call counter.
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
			->with( 'get_option', 'woocommerce_allow_tracking' )
			->willReturn( $granted ? 'yes' : 'no' );
	}

	public function test_returns_control_without_consent() {
		$this->set_consent( false );
		$captured   = null;
		$experiment = $this->build_experiment( 'treatment_a', 'store_123', $captured );

		$this->assertSame( 'control', $experiment->get_variant() );
		$this->assertNull( $captured, 'abtest must not be constructed without consent' );
	}

	public function test_returns_control_with_empty_assignment_key() {
		$this->set_consent( true );
		$captured   = null;
		$experiment = $this->build_experiment( 'treatment_a', '', $captured );

		$this->assertSame( 'control', $experiment->get_variant() );
		$this->assertNull( $captured, 'abtest must not be constructed without an assignment key' );
	}

	public function test_returns_assigned_variant_and_passes_assignment_key() {
		$this->set_consent( true );
		$captured   = null;
		$experiment = $this->build_experiment( 'treatment_a', 'store_123', $captured );

		$this->assertSame( 'treatment_a', $experiment->get_variant() );
		$this->assertSame( 'store_123', $captured );
	}

	public function test_returns_control_for_unknown_variant() {
		$this->set_consent( true );
		$captured   = null;
		$experiment = $this->build_experiment( 'renamed_arm_typo', 'store_123', $captured );

		$this->assertSame( 'control', $experiment->get_variant() );
	}

	public function test_returns_control_for_non_string_variation() {
		$this->set_consent( true );
		$captured   = null;
		$experiment = $this->build_experiment( null, 'store_123', $captured );

		$this->assertSame( 'control', $experiment->get_variant() );
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
