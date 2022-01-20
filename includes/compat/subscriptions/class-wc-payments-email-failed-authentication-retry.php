<?php
/**
 * Admin email about payment retry failed due to authentication
 *
 * Email sent to admins when an attempt to automatically process a subscription renewal payment has failed
 * with the `authentication_needed` error, and a retry rule has been applied to retry the payment in the future.
 *
 * @extends     WC_Email_Failed_Order
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * An email sent to the admin when payment fails to go through due to authentication_required error.
 */
class WC_Payments_Email_Failed_Authentication_Retry extends WC_Email_Failed_Order {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id          = 'failed_authentication_requested';
		$this->title       = __( 'Payment authentication requested email', 'woocommerce-payments' );
		$this->description = __( 'Payment authentication requested emails are sent to chosen recipient(s) when an attempt to automatically process a subscription renewal payment fails because the transaction requires an SCA verification, the customer is requested to authenticate the payment, and a retry rule has been applied to notify the customer again within a certain time period.', 'woocommerce-payments' );

		$this->heading = __( 'Automatic renewal payment failed due to authentication required', 'woocommerce-payments' );
		$this->subject = __( '[{site_title}] Automatic payment failed for {order_number}. Customer asked to authenticate payment and will be notified again {retry_time}', 'woocommerce-payments' );

		$this->template_html  = 'failed-renewal-authentication-requested.php';
		$this->template_plain = 'plain/failed-renewal-authentication-requested.php';
		$this->template_base  = __DIR__ . '/emails/';

		$this->recipient = $this->get_option( 'recipient', get_option( 'admin_email' ) );

		// We want all the parent's methods, with none of its properties, so call its parent's constructor, rather than my parent constructor.
		WC_Email::__construct();
	}

	/**
	 * Get the default e-mail subject.
	 *
	 * @return string
	 */
	public function get_default_subject() {
		return $this->subject;
	}

	/**
	 * Get the default e-mail heading.
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return $this->heading;
	}

	/**
	 * Trigger.
	 *
	 * @param int           $order_id The order ID.
	 * @param WC_Order|null $order Order object.
	 */
	public function trigger( $order_id, $order = null ) {
		$this->object = $order;

		$this->find['retry-time'] = '{retry_time}';
		if ( class_exists( 'WCS_Retry_Manager' ) && function_exists( 'wcs_get_human_time_diff' ) ) {
			$this->retry                 = WCS_Retry_Manager::store()->get_last_retry_for_order( wcs_get_objects_property( $order, 'id' ) );
			$this->replace['retry-time'] = wcs_get_human_time_diff( $this->retry->get_time() );
		} else {
			WC_Stripe_Logger::log( 'WCS_Retry_Manager class or does not exist. Not able to send admin email about customer notification for authentication required for renewal payment.' );
			return;
		}

		$this->find['order-number']    = '{order_number}';
		$this->replace['order-number'] = $this->object->get_order_number();

		if ( ! $this->is_enabled() || ! $this->get_recipient() ) {
			return;
		}

		$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
	}

	/**
	 * Get content html.
	 *
	 * @return string
	 */
	public function get_content_html() {
		return wc_get_template_html(
			$this->template_html,
			[
				'order'         => $this->object,
				'retry'         => $this->retry,
				'email_heading' => $this->get_heading(),
				'sent_to_admin' => true,
				'plain_text'    => false,
				'email'         => $this,
			],
			'',
			$this->template_base
		);
	}

	/**
	 * Get content plain.
	 *
	 * @return string
	 */
	public function get_content_plain() {
		return wc_get_template_html(
			$this->template_plain,
			[
				'order'         => $this->object,
				'retry'         => $this->retry,
				'email_heading' => $this->get_heading(),
				'sent_to_admin' => true,
				'plain_text'    => true,
				'email'         => $this,
			],
			'',
			$this->template_base
		);
	}
}
