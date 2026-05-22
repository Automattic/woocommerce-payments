<?php
/**
 * Class WC_Payments_Email_Post_Kyc_Activation_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WC_Payments_Email_Post_Kyc_Activation unit tests.
 */
class WC_Payments_Email_Post_Kyc_Activation_Test extends WCPAY_UnitTestCase {

	/**
	 * @var WC_Payments_Email_Post_Kyc_Activation
	 */
	private $email;

	public function set_up() {
		parent::set_up();

		if ( ! class_exists( 'WC_Payments_Email_Post_Kyc_Activation' ) ) {
			require_once WCPAY_ABSPATH . 'includes/emails/class-wc-payments-email-post-kyc-activation.php';
		}

		$this->email = new WC_Payments_Email_Post_Kyc_Activation();
	}

	public function test_get_cta_url_points_to_marketing_hub(): void {
		$url = $this->email->get_cta_url();

		$this->assertStringContainsString( 'page=wc-admin', $url );
		$this->assertStringContainsString( 'path=', $url );
		$this->assertStringContainsString( 'marketing', $url );
		$this->assertStringContainsString( admin_url( 'admin.php' ), $url );
	}

	public function test_get_cta_url_includes_referrer_params_for_click_tracking(): void {
		$this->email->stage = 14;
		$url                = $this->email->get_cta_url();

		$this->assertStringContainsString( 'wcpay_referrer=post_kyc_email', $url );
		$this->assertStringContainsString( 'wcpay_referrer_stage=14', $url );
	}

	public function test_get_cta_label_returns_promote_my_store(): void {
		$this->assertSame( 'Promote my store', $this->email->get_cta_label() );
	}

	public function test_init_form_fields_removes_heading_field(): void {
		$this->email->init_form_fields();

		$this->assertArrayNotHasKey( 'heading', $this->email->form_fields );
		// Other standard fields should still be present.
		$this->assertArrayHasKey( 'enabled', $this->email->form_fields );
		$this->assertArrayHasKey( 'subject', $this->email->form_fields );
	}

	public function test_trigger_bails_on_invalid_stage(): void {
		// Invalid stage should not mutate $this->stage from its constructor default.
		$this->assertFalse( $this->email->trigger( 99 ) );
		$this->assertSame( 7, $this->email->stage );
	}

	public function test_trigger_records_stage_when_called_with_valid_stage(): void {
		$this->email->trigger( 14 );

		$this->assertSame( 14, $this->email->stage );
	}

	public function test_trigger_returns_true_when_mailer_reports_success(): void {
		add_filter( 'pre_wp_mail', '__return_true' );

		$result = $this->email->trigger( 7 );

		remove_filter( 'pre_wp_mail', '__return_true' );

		$this->assertTrue( $result );
	}

	public function test_trigger_returns_false_when_email_is_disabled(): void {
		add_filter( 'woocommerce_email_enabled_wcpay_post_kyc_activation', '__return_false' );

		// pre_wp_mail set to true to prove the disabled gate short-circuits
		// before send() runs — otherwise this would return true.
		add_filter( 'pre_wp_mail', '__return_true' );

		$result = $this->email->trigger( 7 );

		remove_filter( 'pre_wp_mail', '__return_true' );
		remove_filter( 'woocommerce_email_enabled_wcpay_post_kyc_activation', '__return_false' );

		$this->assertFalse( $result );
	}

	public function test_trigger_returns_false_when_mailer_reports_failure(): void {
		add_filter( 'pre_wp_mail', '__return_false' );

		$result = $this->email->trigger( 7 );

		remove_filter( 'pre_wp_mail', '__return_false' );

		$this->assertFalse( $result );
	}
}
