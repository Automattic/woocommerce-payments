<?php
/**
 * Class WC_Payments_Email_Failed_Renewal_Authentication
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Failed Renewal Authentication Notification.
 *
 * @extends WC_Email
 */
class WC_Payments_Email_Failed_Renewal_Authentication extends WC_Email {
	/**
	 * An instance of the email, which would normally be sent after a failed payment.
	 *
	 * @var WC_Email_Failed_Order
	 */
	public $original_email;

	/**
	 * Constructor.
	 *
	 * @param WC_Email_Failed_Order[] $email_classes All existing instances of WooCommerce emails.
	 */
	public function __construct( $email_classes = [] ) {
		$this->id             = 'failed_renewal_authentication';
		$this->title          = __( 'Failed subscription renewal SCA authentication', 'woocommerce-payments' );
		$this->description    = __( 'Sent to a customer when a renewal fails because the transaction requires an SCA verification. The email contains renewal order information and payment links.', 'woocommerce-payments' );
		$this->customer_email = true;

		$this->template_html  = 'failed-renewal-authentication.php';
		$this->template_plain = 'plain/failed-renewal-authentication.php';
		$this->template_base  = __DIR__ . '/emails/';

		// Triggers the email at the correct hook.
		add_action( 'woocommerce_woocommerce_payments_payment_requires_action', [ $this, 'trigger' ] );

		if ( isset( $email_classes['WCS_Email_Customer_Renewal_Invoice'] ) ) {
			$this->original_email = $email_classes['WCS_Email_Customer_Renewal_Invoice'];
		}

		// We want all the parent's methods, with none of its properties, so call its parent's constructor, rather than my parent constructor.
		parent::__construct();
	}

	/**
	 * Generates the HTML for the email while keeping the `template_base` in mind.
	 *
	 * @return string
	 */
	public function get_content_html() {
		ob_start();
		wc_get_template(
			$this->template_html,
			[
				'order'             => $this->object,
				'email_heading'     => $this->get_heading(),
				'sent_to_admin'     => false,
				'plain_text'        => false,
				'authorization_url' => $this->get_authorization_url( $this->object ),
				'email'             => $this,
			],
			'',
			$this->template_base
		);
		return ob_get_clean();
	}

	/**
	 * Generates the plain text for the email while keeping the `template_base` in mind.
	 *
	 * @return string
	 */
	public function get_content_plain() {
		ob_start();
		wc_get_template(
			$this->template_plain,
			[
				'order'             => $this->object,
				'email_heading'     => $this->get_heading(),
				'sent_to_admin'     => false,
				'plain_text'        => true,
				'authorization_url' => $this->get_authorization_url( $this->object ),
				'email'             => $this,
			],
			'',
			$this->template_base
		);
		return ob_get_clean();
	}

	/**
	 * Generates the URL, which will be used to authenticate the payment.
	 *
	 * @param WC_Order $order The order whose payment needs authentication.
	 * @return string
	 */
	public function get_authorization_url( $order ) {
		return add_query_arg( 'wcpay-confirmation', 1, $order->get_checkout_payment_url( false ) );
	}

	/**
	 * Uses specific fields from `WC_Email_Customer_Invoice` for this email.
	 */
	public function init_form_fields() {
		parent::init_form_fields();
		$base_fields = $this->form_fields;

		$this->form_fields = [
			'enabled'    => [
				'title'   => _x( 'Enable/disable', 'an email notification', 'woocommerce-payments' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable this email notification', 'woocommerce-payments' ),
				'default' => 'yes',
			],

			'subject'    => $base_fields['subject'],
			'heading'    => $base_fields['heading'],
			'email_type' => $base_fields['email_type'],
		];
	}

	/**
	 * Returns the default subject of the email (modifiable in settings).
	 *
	 * @return string
	 */
	public function get_default_subject() {
		return __( 'Payment authorization needed for renewal of {site_title} order {order_number}', 'woocommerce-payments' );
	}

	/**
	 * Returns the default heading of the email (modifiable in settings).
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return __( 'Payment authorization needed for renewal of order {order_number}', 'woocommerce-payments' );
	}

	/**
	 * Triggers the email while also disconnecting the original Subscriptions email.
	 *
	 * @param WC_Order $order The order that is being paid.
	 */
	public function trigger( $order ) {
		if ( function_exists( 'wcs_order_contains_subscription' ) && ( wcs_order_contains_subscription( $order->get_id() ) || wcs_is_subscription( $order->get_id() ) || wcs_order_contains_renewal( $order->get_id() ) ) ) {
			if ( ! $this->is_enabled() ) {
				return;
			}

			$this->object = $order;

			$this->recipient = $order->get_billing_email();

			$this->find['order_date']    = '{order_date}';
			$this->replace['order_date'] = wc_format_datetime( $order->get_date_created() );

			$this->find['order_number']    = '{order_number}';
			$this->replace['order_number'] = $order->get_order_number();

			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );

			// Prevent the renewal email from WooCommerce Subscriptions from being sent.
			if ( isset( $this->original_email ) ) {
				remove_action(
					'woocommerce_generated_manual_renewal_order_renewal_notification',
					[
						$this->original_email,
						'trigger',
					]
				);
				remove_action(
					'woocommerce_order_status_failed_renewal_notification',
					[
						$this->original_email,
						'trigger',
					]
				);
			}

			// Prevent the retry email from WooCommerce Subscriptions from being sent.
			add_filter( 'wcs_get_retry_rule_raw', [ $this, 'prevent_retry_notification_email' ], 100, 3 );

			// Send email to store owner indicating communication is happening with the customer to request authentication.
			add_filter( 'wcs_get_retry_rule_raw', [ $this, 'set_store_owner_custom_email' ], 100, 3 );
		}
	}

	/**
	 * Prevent all customer-facing retry notifications from being sent after this email.
	 *
	 * @param array $rule_array The raw details about the retry rule.
	 * @param int   $retry_number The number of the retry.
	 * @param int   $order_id The ID of the order that needs payment.
	 *
	 * @return array
	 */
	public function prevent_retry_notification_email( $rule_array, $retry_number, $order_id ) {
		if ( wcs_get_objects_property( $this->object, 'id' ) === $order_id ) {
			$rule_array['email_template_customer'] = '';
		}

		return $rule_array;
	}

	/**
	 * Send store owner a different email when the retry is related to an authentication required error.
	 *
	 * @param array $rule_array The raw details about the retry rule.
	 * @param int   $retry_number The number of the retry.
	 * @param int   $order_id The ID of the order that needs payment.
	 *
	 * @return array
	 */
	public function set_store_owner_custom_email( $rule_array, $retry_number, $order_id ) {
		if (
			wcs_get_objects_property( $this->object, 'id' ) === $order_id &&
			'' !== $rule_array['email_template_admin'] // Only send our email if a retry admin email was already going to be sent.
		) {
			$rule_array['email_template_admin'] = 'WC_Payments_Email_Failed_Authentication_Retry';
		}

		return $rule_array;
	}
}
