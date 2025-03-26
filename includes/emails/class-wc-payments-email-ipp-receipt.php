<?php
/**
 * Class WC_Payments_Email_IPP_Receipt file
 *
 * @package WooCommerce\Emails
 *
 * @filter woocommerce_email_preview_dummy_order
 *     Filters the dummy order object used for email previews.
 *     @param WC_Order|bool $order The order object or false.
 *     @return WC_Order The filtered order object.
 *
 * @filter woocommerce_email_preview_dummy_address
 *     Filters the dummy address data used for email previews.
 *     @param array $address The address data array.
 *     @return array Modified address data array with store location details.
 *
 * @filter woocommerce_email_preview_placeholders
 *     Filters the email preview placeholders.
 *     @param array $placeholders Array of email preview placeholders.
 *     @return array Modified array of placeholders with order date and number.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Payments_Email_IPP_Receipt' ) ) :

	/**
	 * In Person Payments Receipt Email.
	 *
	 * An email sent to the customer when a new order is paid for with a card reader.
	 *
	 * @class       WC_Payments_Email_IPP_Receipt
	 * @version     2.0.0
	 * @package     WooCommerce\Classes\Emails
	 * @extends     WC_Email
	 */
	class WC_Payments_Email_IPP_Receipt extends WC_Email {

		/**
		 * Merchant settings
		 *
		 * @var array
		 */
		public $merchant_settings = [];

		/**
		 * Charge
		 *
		 * @var array
		 */
		public $charge = [];

		/**
		 * Constructor.
		 */
		public function __construct() {
			$this->id             = 'new_receipt';
			$this->customer_email = true;
			$this->title          = __( 'New receipt', 'woocommerce-payments' );
			$this->description    = __( 'New receipt emails are sent to customers when a new order is paid for with a card reader.', 'woocommerce-payments' );
			$this->template_base  = WCPAY_ABSPATH . 'templates/';
			$this->template_html  = 'emails/customer-ipp-receipt.php';
			$this->template_plain = 'emails/plain/customer-ipp-receipt.php';
			$this->plugin_id      = 'woocommerce_woocommerce_payments_';
			$this->placeholders   = [
				'{order_date}'   => '',
				'{order_number}' => '',
			];

			// Content hooks.
			add_action( 'woocommerce_payments_email_ipp_receipt_store_details', [ $this, 'store_details' ], 10, 2 );
			add_action( 'woocommerce_payments_email_ipp_receipt_compliance_details', [ $this, 'compliance_details' ], 10, 2 );

			// Triggers for this email.
			add_action( 'woocommerce_payments_email_ipp_receipt_notification', [ $this, 'trigger' ], 10, 3 );

			// WooCommerce Email preview filters.
			add_filter( 'woocommerce_email_preview_dummy_order', [ $this, 'get_preview_order' ], 10, 1 );
			add_filter( 'woocommerce_email_preview_dummy_address', [ $this, 'get_preview_address' ], 10, 1 );
			add_filter( 'woocommerce_email_preview_placeholders', [ $this, 'get_preview_placeholders' ], 10, 1 );

			/**
			 * Please don't move. The call to the parent constructor here is intentional. It allows this class to merge
			 * its placeholders with the parent's and prefix the settings with its own identifier.
			 * Moving this call to the top of the constructor will cause the placeholders to stop working and
			 * lose the woocommerce_payments_ prefix in the settings.
			 *
			 * @see: WC_Email::_construct()
			*/
			parent::__construct();
		}

		/**
		 * Get preview order data for email preview.
		 *
		 * @param WC_Order $order The dummy order instance.
		 * @return WC_Order
		 * @see WC_Email_Preview::get_dummy_order()
		 */
		public function get_preview_order( $order ) {
			$order->set_payment_method_title( __( 'WooCommerce In-Person Payments', 'woocommerce-payments' ) );
			return $order;
		}

		/**
		 * Get preview address data for email preview.
		 *
		 * @param array $address The address data.
		 * @return array
		 */
		public function get_preview_address( $address ) {
			if ( empty( $address ) ) {
				$address = [
					'line1'       => '123 Sample Street',
					'line2'       => 'Suite 100',
					'city'        => 'Sample City',
					'state'       => 'ST',
					'postal_code' => '12345',
					'country'     => 'US',
				];
			}
			return $address;
		}

		/**
		 * Get preview placeholders for email preview.
		 *
		 * @param array $placeholders The placeholders array.
		 * @return array
		 */
		public function get_preview_placeholders( $placeholders ) {
			$placeholders['{order_date}']   = wc_format_datetime( new DateTime() );
			$placeholders['{order_number}'] = '42';
			return $placeholders;
		}

		/**
		 * Get email subject.
		 *
		 * @since  3.1.0
		 * @return string
		 */
		public function get_default_subject(): string {
			return __( 'Your {site_title} Receipt', 'woocommerce-payments' );
		}

		/**
		 * Get email heading.
		 *
		 * @since  3.1.0
		 * @return string
		 */
		public function get_default_heading(): string {
			return __( 'Your receipt for order: #{order_number}', 'woocommerce-payments' );
		}

		/**
		 * Trigger the sending of this email.
		 *
		 * @param WC_Order $order The order instance.
		 * @param array    $merchant_settings The merchant settings data.
		 * @param array    $charge The charge data.
		 */
		public function trigger( WC_Order $order, array $merchant_settings, array $charge ) {
			$this->setup_locale();
			$email_already_sent = false;

			if ( $order instanceof WC_Order ) {
				$this->object                         = $order;
				$this->recipient                      = $this->object->get_billing_email();
				$this->placeholders['{order_date}']   = wc_format_datetime( $this->object->get_date_created() );
				$this->placeholders['{order_number}'] = $this->object->get_order_number();

				$email_already_sent = $order->get_meta( '_new_receipt_email_sent' );
			}

			$this->merchant_settings = $merchant_settings;
			$this->charge            = $charge;

			if ( 'true' !== $email_already_sent && $this->is_enabled() && $this->get_recipient() ) {
				$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );

				$order->update_meta_data( '_new_receipt_email_sent', 'true' );
				$order->save();
			}

			$this->restore_locale();
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
					'order'              => $this->object,
					'merchant_settings'  => $this->merchant_settings,
					'charge'             => $this->charge,
					'email_heading'      => $this->get_heading(),
					'additional_content' => $this->get_additional_content(),
					'sent_to_admin'      => false,
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
					'order'              => $this->object,
					'merchant_settings'  => $this->merchant_settings,
					'charge'             => $this->charge,
					'email_heading'      => $this->get_heading(),
					'additional_content' => $this->get_additional_content(),
					'sent_to_admin'      => false,
					'plain_text'         => true,
					'email'              => $this,
				],
				'',
				WCPAY_ABSPATH . 'templates/'
			);
		}

		/**
		 * Get preview merchant settings for email preview.
		 *
		 * @param array $settings The merchant settings.
		 * @return array
		 */
		public function get_preview_merchant_settings( $settings ) {
			if ( empty( $settings ) ) {
				$settings = [
					'business_name' => 'Sample Store',
					'support_info'  => [
						'address' => [
							'line1'       => '123 Sample Street',
							'line2'       => 'Suite 100',
							'city'        => 'Sample City',
							'state'       => 'ST',
							'postal_code' => '12345',
							'country'     => 'US',
						],
						'phone'   => '+1 (555) 123-4567',
						'email'   => 'support@samplestore.com',
					],
				];
			}
			return $settings;
		}

		/**
		 * Get store details content html
		 *
		 * @param array   $settings The settings.
		 * @param boolean $plain_text Whether the content type is plain text.
		 * @return void
		 */
		public function store_details( array $settings, bool $plain_text ) {
			// Ensure we have all required data for preview.
			$settings = $this->get_preview_merchant_settings( $settings );

			$template_data = [
				'business_name'   => $settings['business_name'] ?? '',
				'support_address' => $settings['support_info']['address'] ?? [],
				'support_phone'   => $settings['support_info']['phone'] ?? '',
				'support_email'   => $settings['support_info']['email'] ?? '',
			];

			if ( $plain_text ) {
				wc_get_template(
					'emails/plain/email-ipp-receipt-store-details.php',
					$template_data,
					'',
					WCPAY_ABSPATH . 'templates/'
				);
			} else {
				wc_get_template(
					'emails/email-ipp-receipt-store-details.php',
					$template_data,
					'',
					WCPAY_ABSPATH . 'templates/'
				);
			}
		}

		/**
		 * Get preview charge data for email preview.
		 *
		 * @param array $charge The charge data.
		 * @return array
		 */
		public function get_preview_charge( $charge ) {
			if ( empty( $charge ) ) {
				$charge = [
					'payment_method_details' => [
						'card_present' => [
							'brand'   => 'visa',
							'last4'   => '4242',
							'receipt' => [
								'application_preferred_name' => 'Sample App',
								'dedicated_file_name' => 'Sample File',
								'account_type'        => 'credit',
							],
						],
					],
				];
			}
			return $charge;
		}

		/**
		 * Get compliance data content html
		 *
		 * @param array   $charge The charge.
		 * @param boolean $plain_text Whether the content type is plain text.
		 * @return void
		 */
		public function compliance_details( array $charge, bool $plain_text ) {
			// Ensure we have all required data for preview.
			$charge = $this->get_preview_charge( $charge );

			$template_data = [
				'payment_method_details' => $charge['payment_method_details']['card_present'] ?? [],
				'receipt'                => $charge['payment_method_details']['card_present']['receipt'] ?? [],
			];

			if ( $plain_text ) {
				wc_get_template(
					'emails/plain/email-ipp-receipt-compliance-details.php',
					$template_data,
					'',
					WCPAY_ABSPATH . 'templates/'
				);
			} else {
				wc_get_template(
					'emails/email-ipp-receipt-compliance-details.php',
					$template_data,
					'',
					WCPAY_ABSPATH . 'templates/'
				);
			}
		}

		/**
		 * Default content to show below main email content.
		 *
		 * @return string
		 */
		public function get_default_additional_content(): string {
			return __( 'Thanks for using {site_url}!', 'woocommerce-payments' );
		}
	}

endif;

return new WC_Payments_Email_IPP_Receipt();
