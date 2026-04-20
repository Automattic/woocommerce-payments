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

use WCPay\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Payments_Email_Failed_Authentication_Retry
 *
 * An email sent to the admin when payment fails to go through due to authentication_required error.
 *
 * @extends WC_Email_Failed_Order
 *
 * @filter woocommerce_email_preview_dummy_order
 *     Filters the dummy order object used for email previews.
 *     @param WC_Order|bool $order The order object or false.
 *     @return WC_Order The filtered order object.
 *
 * @filter woocommerce_email_preview_dummy_retry
 *     Filters the dummy retry object used for email previews.
 *     @param WCS_Retry|bool $retry The retry object or false.
 *     @return WCS_Retry|null The filtered retry object or null if WCS_Retry class doesn't exist.
 *
 * @filter woocommerce_email_preview_placeholders
 *     Filters the email preview placeholders.
 *     @param array $placeholders Array of email preview placeholders.
 *     @return array Modified array of placeholders.
 */
class WC_Payments_Email_Failed_Authentication_Retry extends WC_Email_Failed_Order {

	/**
	 * The details of the last retry (if any) recorded for a given order
	 *
	 * @var WCS_Retry
	 */
	private $retry;

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

		// Add email preview filters.
		add_filter( 'woocommerce_email_preview_dummy_order', [ $this, 'get_preview_order' ], 10, 1 );
		add_filter( 'woocommerce_email_preview_dummy_retry', [ $this, 'get_preview_retry' ], 10, 1 );
		add_filter( 'woocommerce_email_preview_placeholders', [ $this, 'get_preview_placeholders' ], 10, 1 );
	}

	/**
	 * Get a dummy order for email preview.
	 *
	 * @param WC_Order|bool $order The order object or false.
	 * @return WC_Order
	 */
	public function get_preview_order( $order ) {
		if ( ! $order instanceof WC_Order ) {
			$order = wc_create_order();
			$order->set_status( 'failed' );
			$order->set_billing_first_name( 'John' );
			$order->set_billing_last_name( 'Doe' );
			$order->set_billing_email( 'john.doe@example.com' );
			$order->set_total( 99.99 );
			$order->save();
		}
		return $order;
	}

	/**
	 * Get a dummy retry object for email preview.
	 *
	 * @param WCS_Retry|bool $retry The retry object or false.
	 * @return WCS_Retry|null
	 */
	public function get_preview_retry( $retry ) {
		if ( ! class_exists( 'WCS_Retry' ) || ! function_exists( 'wcs_get_human_time_diff' ) ) {
			return null;
		}

		if ( ! $retry instanceof WCS_Retry ) {
			$retry_data = [
				'time'         => time() + DAY_IN_SECONDS,
				'order_id'     => 0,
				'retry_number' => 1,
				'status'       => 'pending',
			];
			$retry      = new WCS_Retry( $retry_data );
		}
		return $retry;
	}

	/**
	 * Get preview placeholders.
	 *
	 * @param array $placeholders The placeholders array.
	 * @return array
	 */
	public function get_preview_placeholders( $placeholders ) {
		$retry      = $this->get_preview_retry( false );
		$retry_time = '';
		if ( $retry && function_exists( 'wcs_get_human_time_diff' ) ) {
			$retry_time = wcs_get_human_time_diff( $retry->get_time() );
		}
		$placeholders['{retry_time}'] = $retry_time;
		return $placeholders;
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
		if ( ! $this->is_enabled() || ! $this->get_recipient() ) {
			return;
		}

		$this->object = $order;

		if ( class_exists( 'WCS_Retry_Manager' ) && function_exists( 'wcs_get_human_time_diff' ) ) {
			$this->retry = WCS_Retry_Manager::store()->get_last_retry_for_order( wcs_get_objects_property( $order, 'id' ) );
		} else {
			Logger::log( 'WCS_Retry_Manager class or does not exist. Not able to send admin email about customer notification for authentication required for renewal payment.' );
			return;
		}

		// Set up order number replacement.
		$this->find['order-number']    = '{order_number}';
		$this->replace['order-number'] = $this->object->get_order_number();

		// Set up retry time replacement.
		$retry_time = '';
		if ( $this->retry && function_exists( 'wcs_get_human_time_diff' ) ) {
			$retry_time = wcs_get_human_time_diff( $this->retry->get_time() );
		}
		$this->placeholders['{retry_time}'] = $retry_time;

		$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
	}

	/**
	 * Get content html.
	 *
	 * @return string
	 */
	public function get_content_html() {
		// Ensure retry object is initialized for preview.
		if ( ! isset( $this->retry ) ) {
			$this->retry = $this->get_preview_retry( false );
		}

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
		// Ensure retry object is initialized for preview.
		if ( ! isset( $this->retry ) ) {
			$this->retry = $this->get_preview_retry( false );
		}

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
