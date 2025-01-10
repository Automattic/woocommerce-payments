<?php
/**
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/customer-ipp-receipt.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Payments\Templates\Emails
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Output the email header.
 *
 * @hooked WC_Emails::email_header() Output the email header
 * @since 4.0.0
 */
do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<?php /* translators: %s: Customer first name */ ?>
<p><?php printf( esc_html__( 'Hi %s,', 'woocommerce-payments' ), esc_html( $order->get_billing_first_name() ) ); ?></p>
<?php /* translators: %s: Order number */ ?>
<p><?php printf( esc_html__( 'This is the receipt for your order #%s:', 'woocommerce-payments' ), esc_html( $order->get_order_number() ) ); ?></p>
<?php
/**
 * Output the store details section of the IPP receipt email.
 *
 * @since 4.0.0
 */
do_action( 'woocommerce_payments_email_ipp_receipt_store_details', $merchant_settings, $plain_text );

/**
 * Output the order details section of the email.
 *
 * @hooked WC_Emails::order_details() Shows the order details table.
 * @hooked WC_Structured_Data::generate_order_data() Generates structured data.
 * @hooked WC_Structured_Data::output_structured_data() Outputs structured data.
 * @since 2.5.0
 */
do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );

/**
 * Output the compliance details section of the IPP receipt email.
 *
 * @hooked WC_Payments_Email_IPP_Receipt::compliance_details() Output receipt compliance details
 * @since 4.0.0
 */
do_action( 'woocommerce_payments_email_ipp_receipt_compliance_details', $charge, $plain_text );

/**
 * Output the order meta data section of the email.
 *
 * @hooked WC_Emails::order_meta() Shows order meta data.
 * @since 4.0.0
 */
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );

/**
 * Show user-defined additional content - this is set in each email's settings.
 *
 * @since 4.0.0
 */
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

/**
 * Output the email footer.
 *
 * @hooked WC_Emails::email_footer() Output the email footer
 * @since 4.0.0
 */
do_action( 'woocommerce_email_footer', $email );
