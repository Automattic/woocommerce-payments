<?php
/**
 * Class WC_Payments_Email_Post_Kyc_Activation file
 *
 * @package WooCommerce\Emails
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Payments_Email_Post_Kyc_Activation' ) ) :

	/**
	 * Post-KYC Activation Email.
	 *
	 * @deprecated 11.2.0 First-sale reminders have been retired. This class cannot send reminders.
	 */
	class WC_Payments_Email_Post_Kyc_Activation extends WC_Email {

		/**
		 * Stage of the nudge sequence (7, 14, or 30).
		 *
		 * @var int
		 */
		public $stage = 7;

		/**
		 * Constructor.
		 */
		public function __construct() {
			$this->id             = 'wcpay_post_kyc_activation';
			$this->customer_email = false;
			$this->title          = __( 'First sale reminder', 'woocommerce-payments' );
			$this->description    = __( "We'll send a couple of reminders during your first month of accepting payments, to help you bring in your first sale. Stops automatically once you've taken one.", 'woocommerce-payments' );
			$this->template_base  = WCPAY_ABSPATH . 'templates/';
			$this->plugin_id      = 'woocommerce_woocommerce_payments_';
			$this->placeholders   = [
				'{stage}'      => '',
				'{site_title}' => $this->get_blogname(),
			];

			parent::__construct();

			$this->recipient = $this->get_option( 'recipient', get_option( 'admin_email' ) );
		}

		/**
		 * Get default subject.
		 *
		 * @return string
		 */
		public function get_default_subject(): string {
			return __( 'Ready for your first sale on {site_title}?', 'woocommerce-payments' );
		}

		/**
		 * Get default heading.
		 *
		 * @return string
		 */
		public function get_default_heading(): string {
			return __( 'Your store is ready — let’s make your first sale', 'woocommerce-payments' );
		}

		/**
		 * Hide the heading form field from WC Settings → Emails. The template
		 * renders per-stage hard-coded headings, so a merchant override would
		 * silently flatten all three stages to the same heading.
		 */
		public function init_form_fields() {
			parent::init_form_fields();
			unset( $this->form_fields['heading'] );
		}

		/**
		 * Compatibility entry point. No email is sent.
		 *
		 * @param int $stage Legacy reminder stage.
		 * @return bool Always false.
		 */
		public function trigger( int $stage ): bool { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- Preserve the legacy parameter name.
			return false;
		}

		/**
		 * Returns the absolute URL the email CTA button links to.
		 * Mirrors the in-app notice's "Promote my store" destination, plus
		 * referrer params consumed by the click handler on admin_init.
		 *
		 * @return string
		 */
		public function get_cta_url(): string {
			return add_query_arg(
				[
					'page'                 => 'wc-admin',
					'path'                 => '/marketing',
					'wcpay_referrer'       => 'post_kyc_email',
					'wcpay_referrer_stage' => $this->stage,
				],
				admin_url( 'admin.php' )
			);
		}

		/**
		 * Label for the email CTA button. Matches the in-app notice's CTA label.
		 *
		 * @return string
		 */
		public function get_cta_label(): string {
			return __( 'Promote my store', 'woocommerce-payments' );
		}

		/**
		 * Get content html.
		 *
		 * @return string
		 */
		public function get_content_html(): string {
			return '';
		}

		/**
		 * Get content plain.
		 *
		 * @return string
		 */
		public function get_content_plain(): string {
			return '';
		}

		/**
		 * Default additional content.
		 *
		 * @return string
		 */
		public function get_default_additional_content(): string {
			return __( 'Thanks for choosing WooPayments.', 'woocommerce-payments' );
		}
	}

endif;

return new WC_Payments_Email_Post_Kyc_Activation();
