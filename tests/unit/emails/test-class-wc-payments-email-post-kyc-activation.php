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
		$this->email->trigger( 99 );

		$this->assertSame( 7, $this->email->stage );
	}

	public function test_trigger_records_stage_when_called_with_valid_stage(): void {
		$this->email->trigger( 14 );

		$this->assertSame( 14, $this->email->stage );
	}
}
