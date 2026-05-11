<?php
/**
 * Class Onboarding_Experiment_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Experimental_Abtest;
use WCPay\Onboarding_Experiment;

/**
 * WCPay\Onboarding_Experiment unit tests.
 */
class Onboarding_Experiment_Test extends WCPAY_UnitTestCase {

	/**
	 * @var int
	 */
	private $user_id;

	public function set_up() {
		parent::set_up();

		$this->user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $this->user_id );
	}

	public function tear_down() {
		delete_user_meta( $this->user_id, Onboarding_Experiment::USER_META_VARIATION_KEY );
		delete_user_meta( $this->user_id, Onboarding_Experiment::USER_META_BYPASS_KEY );
		delete_user_meta( $this->user_id, Onboarding_Experiment::USER_META_EXPOSED_KEY );
		delete_user_meta( $this->user_id, Onboarding_Experiment::USER_META_ANON_ID_KEY );
		parent::tear_down();
	}

	public function test_get_variation_returns_cached_user_meta_without_hitting_abtest() {
		update_user_meta( $this->user_id, Onboarding_Experiment::USER_META_VARIATION_KEY, 'treatment' );

		$abtest = $this->createMock( Experimental_Abtest::class );
		$abtest->expects( $this->never() )->method( 'get_variation' );

		$experiment = new Onboarding_Experiment( $abtest );

		$this->assertSame( 'treatment', $experiment->get_variation() );
	}

	public function test_get_variation_fetches_from_abtest_and_persists_to_user_meta() {
		$abtest = $this->createMock( Experimental_Abtest::class );
		$abtest->expects( $this->once() )
			->method( 'get_variation' )
			->with( Onboarding_Experiment::EXPERIMENT_NAME )
			->willReturn( 'treatment' );

		$experiment = new Onboarding_Experiment( $abtest );

		$this->assertSame( 'treatment', $experiment->get_variation() );
		$this->assertSame( 'treatment', get_user_meta( $this->user_id, Onboarding_Experiment::USER_META_VARIATION_KEY, true ) );
	}

	public function test_get_variation_returns_transient_control_without_persisting_when_abtest_returns_null() {
		$abtest = $this->createMock( Experimental_Abtest::class );
		$abtest->method( 'get_variation' )->willReturn( null );

		$experiment = new Onboarding_Experiment( $abtest );

		$this->assertSame( 'control', $experiment->get_variation() );
		$this->assertSame( '', get_user_meta( $this->user_id, Onboarding_Experiment::USER_META_VARIATION_KEY, true ) );
	}

	public function test_get_variation_returns_transient_control_without_persisting_when_abtest_returns_empty_string() {
		$abtest = $this->createMock( Experimental_Abtest::class );
		$abtest->method( 'get_variation' )->willReturn( '' );

		$experiment = new Onboarding_Experiment( $abtest );

		$this->assertSame( 'control', $experiment->get_variation() );
		$this->assertSame( '', get_user_meta( $this->user_id, Onboarding_Experiment::USER_META_VARIATION_KEY, true ) );
	}

	public function test_get_variation_returns_transient_control_without_persisting_when_abtest_returns_unknown_arm() {
		$abtest = $this->createMock( Experimental_Abtest::class );
		$abtest->method( 'get_variation' )->willReturn( 'experimental_arm_v2' );

		$experiment = new Onboarding_Experiment( $abtest );

		$this->assertSame( 'control', $experiment->get_variation() );
		$this->assertSame( '', get_user_meta( $this->user_id, Onboarding_Experiment::USER_META_VARIATION_KEY, true ) );
	}

	public function test_get_variation_retries_abtest_after_transient_failure() {
		$abtest = $this->createMock( Experimental_Abtest::class );
		$abtest->expects( $this->exactly( 2 ) )
			->method( 'get_variation' )
			->willReturnOnConsecutiveCalls( null, 'treatment' );

		$experiment = new Onboarding_Experiment( $abtest );

		$this->assertSame( 'control', $experiment->get_variation() );
		$this->assertSame( 'treatment', $experiment->get_variation() );
		$this->assertSame( 'treatment', get_user_meta( $this->user_id, Onboarding_Experiment::USER_META_VARIATION_KEY, true ) );
	}

	public function test_has_assigned_variation_returns_false_before_any_assignment() {
		$experiment = new Onboarding_Experiment( $this->createMock( Experimental_Abtest::class ) );

		$this->assertFalse( $experiment->has_assigned_variation() );
	}

	public function test_has_assigned_variation_returns_true_after_successful_assignment() {
		$abtest = $this->createMock( Experimental_Abtest::class );
		$abtest->method( 'get_variation' )->willReturn( 'treatment' );

		$experiment = new Onboarding_Experiment( $abtest );
		$experiment->get_variation();

		$this->assertTrue( $experiment->has_assigned_variation() );
	}

	public function test_has_assigned_variation_returns_false_after_abtest_failure() {
		$abtest = $this->createMock( Experimental_Abtest::class );
		$abtest->method( 'get_variation' )->willReturn( null );

		$experiment = new Onboarding_Experiment( $abtest );
		$experiment->get_variation();

		$this->assertFalse( $experiment->has_assigned_variation() );
	}

	public function test_has_assigned_variation_without_logged_in_user_returns_false() {
		update_user_meta( $this->user_id, Onboarding_Experiment::USER_META_VARIATION_KEY, 'treatment' );
		wp_set_current_user( 0 );

		$experiment = new Onboarding_Experiment( $this->createMock( Experimental_Abtest::class ) );

		$this->assertFalse( $experiment->has_assigned_variation() );
	}

	public function test_has_bypass_returns_false_when_meta_absent() {
		$experiment = new Onboarding_Experiment( $this->createMock( Experimental_Abtest::class ) );

		$this->assertFalse( $experiment->has_bypass() );
	}

	public function test_has_bypass_returns_true_when_meta_set() {
		update_user_meta( $this->user_id, Onboarding_Experiment::USER_META_BYPASS_KEY, 1 );

		$experiment = new Onboarding_Experiment( $this->createMock( Experimental_Abtest::class ) );

		$this->assertTrue( $experiment->has_bypass() );
	}

	public function test_set_bypass_persists_user_meta() {
		$experiment = new Onboarding_Experiment( $this->createMock( Experimental_Abtest::class ) );

		$experiment->set_bypass();

		$this->assertSame( '1', get_user_meta( $this->user_id, Onboarding_Experiment::USER_META_BYPASS_KEY, true ) );
	}

	public function test_has_recorded_exposure_returns_false_when_meta_absent() {
		$experiment = new Onboarding_Experiment( $this->createMock( Experimental_Abtest::class ) );

		$this->assertFalse( $experiment->has_recorded_exposure() );
	}

	public function test_has_recorded_exposure_returns_true_when_meta_set() {
		update_user_meta( $this->user_id, Onboarding_Experiment::USER_META_EXPOSED_KEY, 1 );

		$experiment = new Onboarding_Experiment( $this->createMock( Experimental_Abtest::class ) );

		$this->assertTrue( $experiment->has_recorded_exposure() );
	}

	public function test_mark_exposed_persists_user_meta() {
		$experiment = new Onboarding_Experiment( $this->createMock( Experimental_Abtest::class ) );

		$experiment->mark_exposed();

		$this->assertSame( '1', get_user_meta( $this->user_id, Onboarding_Experiment::USER_META_EXPOSED_KEY, true ) );
	}

	public function test_has_recorded_exposure_without_logged_in_user_returns_false() {
		update_user_meta( $this->user_id, Onboarding_Experiment::USER_META_EXPOSED_KEY, 1 );
		wp_set_current_user( 0 );

		$experiment = new Onboarding_Experiment( $this->createMock( Experimental_Abtest::class ) );

		$this->assertFalse( $experiment->has_recorded_exposure() );
	}

	public function test_mark_exposed_without_logged_in_user_is_noop() {
		wp_set_current_user( 0 );

		$experiment = new Onboarding_Experiment( $this->createMock( Experimental_Abtest::class ) );

		$experiment->mark_exposed();

		$this->assertSame( '', get_user_meta( $this->user_id, Onboarding_Experiment::USER_META_EXPOSED_KEY, true ) );
	}

	public function test_get_variation_without_logged_in_user_falls_back_to_control_without_persisting() {
		wp_set_current_user( 0 );

		$abtest = $this->createMock( Experimental_Abtest::class );
		$abtest->method( 'get_variation' )->willReturn( null );

		$experiment = new Onboarding_Experiment( $abtest );

		$this->assertSame( 'control', $experiment->get_variation() );
		$this->assertSame( '', get_user_meta( $this->user_id, Onboarding_Experiment::USER_META_VARIATION_KEY, true ) );
	}

	public function test_has_bypass_without_logged_in_user_returns_false() {
		update_user_meta( $this->user_id, Onboarding_Experiment::USER_META_BYPASS_KEY, 1 );
		wp_set_current_user( 0 );

		$experiment = new Onboarding_Experiment( $this->createMock( Experimental_Abtest::class ) );

		$this->assertFalse( $experiment->has_bypass() );
	}
}
