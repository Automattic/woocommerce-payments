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
	 * Sent to merchants on day 7, 14, and 30 after KYC completion when they have not yet made their first sale.
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
			$this->template_html  = 'emails/post-kyc-activation.php';
			$this->template_plain = 'emails/plain/post-kyc-activation.php';
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
		 * Trigger sending the email.
		 *
		 * Returns whether `$this->send()` actually delivered the message. Returns
		 * false in three cases: invalid stage, opted out (email disabled or no
		 * recipient), or the underlying mailer rejected the send. The caller uses
		 * this signal to decide whether the stage should be considered consumed.
		 *
		 * @param int $stage The stage day (7, 14, or 30).
		 * @return bool True if the mailer reported a successful send, false otherwise.
		 */
		public function trigger( int $stage ): bool {
			if ( ! in_array( $stage, [ 7, 14, 30 ], true ) ) {
				return false;
			}

			$this->stage                   = $stage;
			$this->placeholders['{stage}'] = (string) $stage;

			$this->setup_locale();

			$sent = false;
			if ( $this->is_enabled() && $this->get_recipient() ) {
				$sent = $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
			}

			$this->restore_locale();

			if ( class_exists( 'WC_Tracks' ) ) {
				if ( $sent ) {
					WC_Tracks::record_event( 'wcpay_post_kyc_activation_email_sent', [ 'stage' => $stage ] );
				} elseif ( $this->is_enabled() && $this->get_recipient() ) {
					// Mailer rejected the send — distinct from an intentional opt-out.
					WC_Tracks::record_event( 'wcpay_post_kyc_activation_email_send_failed', [ 'stage' => $stage ] );
				}
			}

			return $sent;
		}

		/**
		 * Returns the absolute URL the email CTA button links to.
		 * Mirrors the in-app banner's "Promote my store" destination, plus
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
		 * Label for the email CTA button. Matches the in-app banner's CTA label.
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
			return wc_get_template_html(
				$this->template_html,
				[
					'stage'              => $this->stage,
					'email_heading'      => $this->get_heading(),
					'additional_content' => $this->get_additional_content(),
					'cta_url'            => $this->get_cta_url(),
					'cta_label'          => $this->get_cta_label(),
					'sent_to_admin'      => true,
					'plain_text'         => false,
					'email'              => $this,
				],
				'',
				WCPAY_ABSPATH . 'templates/'
			);
		}

		/**
		 * Get content plain.
		 *
		 * @return string
		 */
		public function get_content_plain(): string {
			return wc_get_template_html(
				$this->template_plain,
				[
					'stage'              => $this->stage,
					'email_heading'      => $this->get_heading(),
					'additional_content' => $this->get_additional_content(),
					'cta_url'            => $this->get_cta_url(),
					'cta_label'          => $this->get_cta_label(),
					'sent_to_admin'      => true,
					'plain_text'         => true,
					'email'              => $this,
				],
				'',
				WCPAY_ABSPATH . 'templates/'
			);
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
