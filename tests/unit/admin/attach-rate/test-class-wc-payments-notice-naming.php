<?php
/**
 * Class WC_Payments_Notice_Naming_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WC_Payments_Notice_Naming unit tests.
 *
 * Pins every slug-derived identifier and the override-as-data escape hatch in
 * one place, with no live notice instance — the whole point of extracting the
 * derivation out of the lifecycle base.
 */
class WC_Payments_Notice_Naming_Test extends WCPAY_UnitTestCase {

	public function test_derives_every_key_from_a_single_word_slug(): void {
		$naming = new WC_Payments_Notice_Naming( 'one_and_done' );

		$this->assertSame( 'wcpay_one_and_done_notice_dismissed', $naming->dismissed_meta_key() );
		$this->assertSame( 'wcpay_one_and_done_notice_snoozed', $naming->snoozed_meta_key() );
		$this->assertSame( 'wcpay_one_and_done_notice_shown', $naming->shown_meta_key() );
		$this->assertSame( 'wcpay_one_and_done_eligible', $naming->eligibility_transient_key() );
		$this->assertSame( 'WCPAY_ONE_AND_DONE_NOTICE', $naming->script_handle() );
		$this->assertSame( 'wc-payments-one-and-done-notice', $naming->dist_name() );
		$this->assertSame( 'wcpay-one-and-done-notice', $naming->mount_div_id() );
		$this->assertSame( 'wcpayOneAndDoneNoticeSettings', $naming->localize_var_name() );
		$this->assertSame( 'wcpay-one-and-done-cta', $naming->cta_query_arg() );
		$this->assertSame( 'wcpay-hide-one-and-done-notice', $naming->hide_query_arg() );
		$this->assertSame( 'wcpay-snooze-one-and-done-notice', $naming->snooze_query_arg() );
		$this->assertSame( 'wcpay_one_and_done_cta_nonce', $naming->cta_nonce_action() );
		$this->assertSame( 'wcpay_hide_one_and_done_notice_nonce', $naming->hide_nonce_action() );
		$this->assertSame( 'wcpay_snooze_one_and_done_notice_nonce', $naming->snooze_nonce_action() );
		$this->assertSame( '_wcpay_one_and_done_cta_nonce', $naming->cta_nonce_arg() );
		$this->assertSame( '_wcpay_one_and_done_notice_nonce', $naming->hide_nonce_arg() );
		$this->assertSame( '_wcpay_snooze_one_and_done_notice_nonce', $naming->snooze_nonce_arg() );
		$this->assertSame( 'wcpay_one_and_done_notice_shown', $naming->shown_event_name() );
		$this->assertSame( 'wcpay_one_and_done_notice_dismissed', $naming->dismissed_event_name() );
		$this->assertSame( 'wcpay_one_and_done_notice_snoozed', $naming->snoozed_event_name() );
		$this->assertSame( 'wcpay_one_and_done_notice_cta_clicked', $naming->cta_event_name() );
	}

	public function test_kebab_and_camel_derivation_for_multi_word_slug(): void {
		$naming = new WC_Payments_Notice_Naming( 'post_kyc_activation' );

		$this->assertSame( 'wcpay-post-kyc-activation-notice', $naming->mount_div_id() );
		$this->assertSame( 'wcpayPostKycActivationNoticeSettings', $naming->localize_var_name() );
		$this->assertSame( 'WCPAY_POST_KYC_ACTIVATION_NOTICE', $naming->script_handle() );
	}

	public function test_override_pins_a_single_key_and_leaves_the_rest_derived(): void {
		$naming = new WC_Payments_Notice_Naming(
			'one_and_done',
			[ 'dismissed_meta_key' => 'wcpay_one_and_done_notice_dismissed_at' ]
		);

		$this->assertSame( 'wcpay_one_and_done_notice_dismissed_at', $naming->dismissed_meta_key() );

		// The override doesn't bleed into the sibling Tracks event, which keeps deriving.
		$this->assertSame( 'wcpay_one_and_done_notice_dismissed', $naming->dismissed_event_name() );
		$this->assertSame( 'wcpay_one_and_done_notice_snoozed', $naming->snoozed_meta_key() );
	}
}
