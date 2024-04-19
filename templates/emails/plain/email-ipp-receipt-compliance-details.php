<?php
/**
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/plain/email-ipp-receipt-compliance-details.php
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Payments\Templates\Emails\Plain
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

echo esc_html( sprintf( "%s:\t%s", __( 'Payment Method', 'woocommerce-payments' ), sprintf( '%s - %s', ucfirst( $payment_method_details['brand'] ), $payment_method_details['last4'] ) ) ) . "\n";

echo esc_html( sprintf( "%s:\t%s", __( 'Application Name', 'woocommerce-payments' ), ucfirst( $receipt['application_preferred_name'] ) ) ) . "\n";

echo esc_html( sprintf( "%s:\t%s", __( 'AID', 'woocommerce-payments' ), ucfirst( $receipt['dedicated_file_name'] ) ) ) . "\n";

echo esc_html( sprintf( "%s:\t%s", __( 'Account Type', 'woocommerce-payments' ), ucfirst( $receipt['account_type'] ) ) ) . "\n";
