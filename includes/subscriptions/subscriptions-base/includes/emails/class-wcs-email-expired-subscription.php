<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Expired Subscription Email
 *
 * An email sent to the admin when a subscription is expired.
 *
 * @class   WCS_Email_Expired_Subscription
 * @version 2.1
 * @package WooCommerce_Subscriptions/Classes/Emails
 * @author  Prospress
 * @extends WC_Email
 */
class WCS_Email_Expired_Subscription extends WC_Email {

	/**
	 * Create an instance of the class.
	 *
	 * @access public
	 */
	function __construct() {

		$this->id          = 'expired_subscription';
		$this->title       = __( 'Expired Subscription', 'woocommerce-subscriptions' );
		$this->description = __( 'Expired Subscription emails are sent when a customer\'s subscription expires.', 'woocommerce-subscriptions' );

		$this->heading     = __( 'Subscription Expired', 'woocommerce-subscriptions' );
		// translators: placeholder is {blogname}, a variable that will be substituted when email is sent out
		$this->subject     = sprintf( _x( '[%s] Subscription Expired', 'default email subject for expired emails sent to the admin', 'woocommerce-subscriptions' ), '{blogname}' );

		$this->template_html  = 'emails/expired-subscription.php';
		$this->template_plain = 'emails/plain/expired-subscription.php';
		$this->template_base  = WC_Subscriptions_Base_Plugin::instance()->get_base_plugin_directory( 'templates/' );

		add_action( 'expired_subscription_notification', array( $this, 'trigger' ) );

		parent::__construct();

		$this->recipient = $this->get_option( 'recipient' );

		if ( ! $this->recipient ) {
			$this->recipient = get_option( 'admin_email' );
		}
	}

	/**
	 * Get the default e-mail subject.
	 *
	 * @since 2.5.3
	 * @return string
	 */
	public function get_default_subject() {
		return $this->subject;
	}

	/**
	 * Get the default e-mail heading.
	 *
	 * @since 2.5.3
	 * @return string
	 */
	public function get_default_heading() {
		return $this->heading;
	}

	/**
	 * trigger function.
	 *
	 * @access public
	 * @return void
	 */
	function trigger( $subscription ) {
		$this->object = $subscription;

		if ( ! is_object( $subscription ) ) {
			throw new InvalidArgumentException( __( 'Subscription argument passed in is not an object.', 'woocommerce-subscriptions' ) );
		}

		if ( ! $this->is_enabled() || ! $this->get_recipient() ) {
			return;
		}

		$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
	}

	/**
	 * get_content_html function.
	 *
	 * @access public
	 * @return string
	 */
	function get_content_html() {
		return wc_get_template_html(
			$this->template_html,
			array(
				'subscription'       => $this->object,
				'email_heading'      => $this->get_heading(),
				'additional_content' => is_callable( array( $this, 'get_additional_content' ) ) ? $this->get_additional_content() : '', // WC 3.7 introduced an additional content field for all emails.
				'sent_to_admin'      => true,
				'plain_text'         => false,
				'email'              => $this,
			),
			'',
			$this->template_base
		);
	}

	/**
	 * get_content_plain function.
	 *
	 * @access public
	 * @return string
	 */
	function get_content_plain() {
		return wc_get_template_html(
			$this->template_plain,
			array(
				'subscription'       => $this->object,
				'email_heading'      => $this->get_heading(),
				'additional_content' => is_callable( array( $this, 'get_additional_content' ) ) ? $this->get_additional_content() : '', // WC 3.7 introduced an additional content field for all emails.
				'sent_to_admin'      => true,
				'plain_text'         => true,
				'email'              => $this,
			),
			'',
			$this->template_base
		);
	}

	/**
	 * Initialise Settings Form Fields
	 *
	 * @access public
	 * @return void
	 */
	function init_form_fields() {
		$this->form_fields = array(
			'enabled'    => array(
				'title'   => _x( 'Enable/Disable', 'an email notification', 'woocommerce-subscriptions' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable this email notification', 'woocommerce-subscriptions' ),
				'default' => 'no',
			),
			'recipient'  => array(
				'title'       => _x( 'Recipient(s)', 'of an email', 'woocommerce-subscriptions' ),
				'type'        => 'text',
				// translators: placeholder is admin email
				'description' => sprintf( __( 'Enter recipients (comma separated) for this email. Defaults to %s.', 'woocommerce-subscriptions' ), '<code>' . esc_html( get_option( 'admin_email' ) ) . '</code>' ),
				'placeholder' => '',
				'default'     => '',
			),
			'subject'    => array(
				'title'       => _x( 'Subject', 'of an email', 'woocommerce-subscriptions' ),
				'type'        => 'text',
				// translators: %s: default e-mail subject.
				'description' => sprintf( __( 'This controls the email subject line. Leave blank to use the default subject: %s.', 'woocommerce-subscriptions' ), '<code>' . $this->subject . '</code>' ),
				'placeholder' => $this->get_default_subject(),
				'default'     => '',
			),
			'heading'    => array(
				'title'       => _x( 'Email Heading', 'Name the setting that controls the main heading contained within the email notification', 'woocommerce-subscriptions' ),
				'type'        => 'text',
				// translators: %s: default e-mail heading.
				'description' => sprintf( __( 'This controls the main heading contained within the email notification. Leave blank to use the default heading: <code>%s</code>.', 'woocommerce-subscriptions' ), $this->heading ),
				'placeholder' => $this->get_default_heading(),
				'default'     => '',
			),
			'email_type' => array(
				'title'       => _x( 'Email type', 'text, html or multipart', 'woocommerce-subscriptions' ),
				'type'        => 'select',
				'description' => __( 'Choose which format of email to send.', 'woocommerce-subscriptions' ),
				'default'     => 'html',
				'class'       => 'email_type',
				'options'     => array(
					'plain'     => _x( 'Plain text', 'email type', 'woocommerce-subscriptions' ),
					'html'      => _x( 'HTML', 'email type', 'woocommerce-subscriptions' ),
					'multipart' => _x( 'Multipart', 'email type', 'woocommerce-subscriptions' ),
				),
			),
		);
	}
}
