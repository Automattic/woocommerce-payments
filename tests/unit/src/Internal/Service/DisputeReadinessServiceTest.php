<?php
/**
 * Class DisputeReadinessServiceTest
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Tests\Internal\Service;

use WC_Payments_Account;
use WCPay\Internal\Service\DisputeReadinessService;
use WCPAY_UnitTestCase;

/**
 * DisputeReadinessService unit tests.
 */
class DisputeReadinessServiceTest extends WCPAY_UnitTestCase {
	/**
	 * Service under test.
	 *
	 * @var DisputeReadinessService
	 */
	private $service;

	public function set_up() {
		parent::set_up();

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
		delete_option( DisputeReadinessService::DISMISSAL_OPTION );
		delete_option( DisputeReadinessService::STATEMENT_DESCRIPTOR_CONFIRMATION_OPTION );

		parent::tear_down();
	}

	public function test_disabled_overview_payload_returns_hidden_dismissed_state() {
		$payload  = $this->service->get_disabled_overview_payload();
		$overview = $payload['overview'];

		$this->assertFalse( $overview['enabled'] );
		$this->assertTrue( $overview['hidden'] );
		$this->assertSame( 0, $overview['score'] );
		$this->assertSame( 0, $overview['total'] );
		$this->assertTrue( $overview['isDismissed'] );
		$this->assertSame( 'feature_disabled', $overview['dismissal']['reappearReason'] );
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

	public function test_refund_policy_signal_uses_fallback_edit_url_when_edit_link_is_empty() {
		$page_id = self::factory()->post->create(
			[
				'post_type'    => 'page',
				'post_status'  => 'draft',
				'post_content' => 'Draft refund policy.',
			]
		);
		update_option( 'woocommerce_refund_returns_page_id', $page_id );

		add_filter( 'get_edit_post_link', '__return_empty_string' );
		$overview = $this->service->get_overview_payload()['overview'];
		remove_filter( 'get_edit_post_link', '__return_empty_string' );
		$signal = $this->get_signal( $overview, 'refund_policy' );

		$this->assertSame( admin_url( 'post.php?post=' . $page_id . '&action=edit' ), $signal['actionUrl'] );
	}

	public function test_terms_signal_uses_woocommerce_advanced_settings_url_when_no_page_is_assigned() {
		delete_option( 'woocommerce_terms_page_id' );

		$overview = $this->service->get_overview_payload()['overview'];
		$signal   = $this->get_signal( $overview, 'terms_and_conditions' );

		$this->assertSame( admin_url( 'admin.php?page=wc-settings&tab=advanced' ), $signal['actionUrl'] );
	}

	public function test_terms_signal_uses_woocommerce_advanced_settings_url_when_page_is_assigned() {
		$page_id = self::factory()->post->create(
			[
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => 'Store terms apply.',
			]
		);
		update_option( 'woocommerce_terms_page_id', $page_id );

		$overview = $this->service->get_overview_payload()['overview'];
		$signal   = $this->get_signal( $overview, 'terms_and_conditions' );

		$this->assertSame( admin_url( 'admin.php?page=wc-settings&tab=advanced' ), $signal['actionUrl'] );
	}

	public function test_statement_descriptor_is_complete_when_it_matches_store_name() {
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

		$this->assertSame( 'complete', $signal['status'] );
		$this->assertSame( 'looks_recognizable', $signal['reason'] );
	}

	public function test_statement_descriptor_handles_generic_store_name_candidate() {
		update_option( 'blogname', 'My Store' );
		$this->mock_account_data(
			[
				'statement_descriptor' => 'Fresh Flowers',
				'business_profile'     => [
					'support_phone' => '+15555555555',
				],
			]
		);

		$overview = $this->service->get_overview_payload()['overview'];
		$signal   = $this->get_signal( $overview, 'statement_descriptor' );

		$this->assertSame( 'complete', $signal['status'] );
		$this->assertSame( 'looks_recognizable', $signal['reason'] );
	}

	public function test_statement_descriptor_prompts_for_review_when_descriptor_is_generic() {
		$this->mock_account_data(
			[
				'statement_descriptor' => 'WOOCOMMERCE PAYMENTS D',
				'business_profile'     => [
					'support_phone' => '+15555555555',
				],
			]
		);

		$overview = $this->service->get_overview_payload()['overview'];
		$signal   = $this->get_signal( $overview, 'statement_descriptor' );

		$this->assertSame( 'incomplete', $signal['status'] );
		$this->assertSame( 'needs_review', $signal['reason'] );
		$this->assertSame( 'Fix it', $signal['actionLabel'] );
		$this->assertSame( 'Make sure your business name appears clearly on customer bank statements to prevent confusion.', $signal['description'] );
		$this->assertSame( "Your statement descriptor will show up on your customers' bank statements. Does it clearly identify your store?", $signal['reviewPrompt']['text'] );
		$this->assertSame( 'WOOCOMMERCE PAYMENTS D', $signal['reviewPrompt']['currentDescriptor'] );
		$this->assertSame( 'Looks good', $signal['reviewPrompt']['confirmLabel'] );
		$this->assertSame( 'Update', $signal['reviewPrompt']['updateLabel'] );
	}

	public function test_confirmed_statement_descriptor_is_complete_for_current_descriptor() {
		$this->mock_account_data(
			[
				'statement_descriptor' => 'WooPayments',
				'business_profile'     => [
					'support_phone' => '+15555555555',
				],
			]
		);

		$overview = $this->service->confirm_statement_descriptor()['overview'];
		$signal   = $this->get_signal( $overview, 'statement_descriptor' );

		$this->assertSame( 'complete', $signal['status'] );
		$this->assertSame( 'confirmed', $signal['reason'] );
		$this->assertArrayNotHasKey( 'reviewPrompt', $signal );
	}

	public function test_statement_descriptor_confirmation_does_not_apply_after_descriptor_changes() {
		$this->mock_account_data(
			[
				'statement_descriptor' => 'WooPayments',
				'business_profile'     => [
					'support_phone' => '+15555555555',
				],
			]
		);
		$this->service->confirm_statement_descriptor();

		$this->mock_account_data(
			[
				'statement_descriptor' => 'My Store',
				'business_profile'     => [
					'support_phone' => '+15555555555',
				],
			]
		);

		$overview = $this->service->get_overview_payload()['overview'];
		$signal   = $this->get_signal( $overview, 'statement_descriptor' );

		$this->assertSame( 'incomplete', $signal['status'] );
		$this->assertSame( 'needs_review', $signal['reason'] );
	}

	public function test_statement_descriptor_is_incomplete_when_account_data_is_not_an_array() {
		$account = $this->createMock( WC_Payments_Account::class );
		$account->method( 'get_cached_account_data' )->willReturn( null );
		$this->service = new DisputeReadinessService( $account );

		$overview = $this->service->get_overview_payload()['overview'];
		$signal   = $this->get_signal( $overview, 'statement_descriptor' );

		$this->assertSame( 'incomplete', $signal['status'] );
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

	public function test_dismissed_card_stays_dismissed_when_state_is_unchanged() {
		$overview = $this->service->dismiss_overview_card()['overview'];

		$this->assertTrue( $overview['isDismissed'] );
		$this->assertTrue( $overview['dismissal']['isStoredDismissal'] );
		$this->assertNull( $overview['dismissal']['reappearReason'] );
		$this->assertSame( $overview['score'], $overview['dismissal']['scoreAtDismissal'] );
		$this->assertSame( $overview['total'], $overview['dismissal']['totalAtDismissal'] );
		$this->assertNotEmpty( $overview['dismissal']['dismissedAt'] );
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

	public function test_dismissed_card_reappears_when_total_changes() {
		update_option(
			DisputeReadinessService::DISMISSAL_OPTION,
			[
				'dismissed'             => true,
				'dismissed_at'          => gmdate( 'c' ),
				'score_at_dismissal'    => 2,
				'total_at_dismissal'    => 3,
				'incomplete_signal_ids' => [],
			],
			false
		);

		$overview = $this->service->get_overview_payload()['overview'];

		$this->assertFalse( $overview['isDismissed'] );
		$this->assertSame( 'total_changed', $overview['dismissal']['reappearReason'] );
	}

	public function test_dismissed_card_reappears_when_incomplete_signals_change_without_score_change() {
		$this->service->dismiss_overview_card();

		$page_id = self::factory()->post->create(
			[
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => 'Refunds are available within 30 days.',
			]
		);
		update_option( 'woocommerce_refund_returns_page_id', $page_id );
		$this->mock_account_data(
			[
				'statement_descriptor' => '',
				'business_profile'     => [
					'support_email' => 'support@example.com',
				],
			]
		);

		$overview = $this->service->get_overview_payload()['overview'];

		$this->assertSame( 2, $overview['score'] );
		$this->assertFalse( $overview['isDismissed'] );
		$this->assertSame( 'incomplete_signals_changed', $overview['dismissal']['reappearReason'] );
	}

	/**
	 * Mocks cached account data.
	 *
	 * @param array $account_data Account data.
	 */
	private function mock_account_data( array $account_data ) {
		$account = $this->createMock( WC_Payments_Account::class );
		$account->method( 'get_cached_account_data' )->willReturn( $account_data );

		$this->service = new DisputeReadinessService( $account );
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
