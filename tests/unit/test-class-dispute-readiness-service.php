<?php
/**
 * Class Dispute_Readiness_Service_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Disputes\Dispute_Readiness_Service;

/**
 * Dispute_Readiness_Service unit tests.
 */
class Dispute_Readiness_Service_Test extends WCPAY_UnitTestCase {
	/**
	 * Service under test.
	 *
	 * @var Dispute_Readiness_Service
	 */
	private $service;

	/**
	 * Original account service.
	 *
	 * @var WC_Payments_Account|null
	 */
	private $original_account_service;

	public function set_up() {
		parent::set_up();

		$this->service                  = new Dispute_Readiness_Service();
		$this->original_account_service = WC_Payments::get_account_service();
		$this->mock_account_data(
			[
				'statement_descriptor' => 'CUSTOM SHOP',
				'business_profile'     => [
					'url'           => 'https://example.com',
					'support_email' => 'support@example.com',
				],
			]
		);
	}

	public function tear_down() {
		delete_option( 'woocommerce_refund_returns_page_id' );
		delete_option( 'woocommerce_terms_page_id' );
		delete_option( Dispute_Readiness_Service::DISMISSAL_OPTION );

		if ( $this->original_account_service ) {
			WC_Payments::set_account_service( $this->original_account_service );
		}

		parent::tear_down();
	}

	public function test_refund_policy_signal_requires_assigned_published_non_empty_page() {
		$page_id = self::factory()->post->create(
			[
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => 'Refunds are available within 30 days.',
			]
		);
		update_option( 'woocommerce_refund_returns_page_id', $page_id );

		$overview = $this->service->get_overview_payload()['overview'];
		$signal   = $this->get_signal( $overview, 'refund_policy' );

		$this->assertSame( 'complete', $signal['status'] );
		$this->assertContains( 'refund_policy', $overview['completeSignalIds'] );
	}

	public function test_terms_signal_treats_empty_published_page_as_incomplete() {
		$page_id = self::factory()->post->create(
			[
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => '   ',
			]
		);
		update_option( 'woocommerce_terms_page_id', $page_id );

		$overview = $this->service->get_overview_payload()['overview'];
		$signal   = $this->get_signal( $overview, 'terms_and_conditions' );

		$this->assertSame( 'incomplete', $signal['status'] );
		$this->assertContains( 'terms_and_conditions', $overview['incompleteSignalIds'] );
	}

	public function test_statement_descriptor_is_incomplete_when_default_like() {
		update_option( 'blogname', 'Example Store' );
		$this->mock_account_data(
			[
				'statement_descriptor' => 'Example Store',
				'business_profile'     => [
					'support_phone' => '+15555555555',
				],
			]
		);

		$overview = $this->service->get_overview_payload()['overview'];
		$signal   = $this->get_signal( $overview, 'statement_descriptor' );

		$this->assertSame( 'incomplete', $signal['status'] );
		$this->assertSame( 'default_like', $signal['reason'] );
	}

	public function test_support_contact_is_complete_when_email_or_phone_exists() {
		$this->mock_account_data(
			[
				'statement_descriptor' => 'CUSTOM SHOP',
				'business_profile'     => [
					'support_phone' => '+15555555555',
				],
			]
		);

		$overview = $this->service->get_overview_payload()['overview'];
		$signal   = $this->get_signal( $overview, 'support_contact' );

		$this->assertSame( 'complete', $signal['status'] );
	}

	public function test_dismissed_card_reappears_when_score_decreases() {
		$this->service->dismiss_overview_card();

		$this->mock_account_data(
			[
				'statement_descriptor' => '',
				'business_profile'     => [],
			]
		);

		$overview = $this->service->get_overview_payload()['overview'];

		$this->assertFalse( $overview['isDismissed'] );
		$this->assertSame( 'score_decreased', $overview['dismissal']['reappearReason'] );
	}

	/**
	 * Mocks cached account data.
	 *
	 * @param array $account_data Account data.
	 */
	private function mock_account_data( array $account_data ) {
		$account = $this->createMock( WC_Payments_Account::class );
		$account->method( 'get_cached_account_data' )->willReturn( $account_data );
		WC_Payments::set_account_service( $account );
	}

	/**
	 * Returns a signal by ID.
	 *
	 * @param array  $overview Overview payload.
	 * @param string $signal_id Signal ID.
	 * @return array
	 */
	private function get_signal( array $overview, string $signal_id ): array {
		foreach ( $overview['signals'] as $signal ) {
			if ( $signal_id === $signal['id'] ) {
				return $signal;
			}
		}

		$this->fail( 'Expected signal was not found.' );
	}
}
