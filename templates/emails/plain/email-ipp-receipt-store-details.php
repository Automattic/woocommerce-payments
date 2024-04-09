<?php
/**
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/plain/email-ipp-receipt-store-details.php
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

echo "==========\n\n";

echo esc_html( $business_name ) . "\n\n";

echo "==========\n\n";

if ( ! empty( $support_address ) ) {
	echo esc_html( $support_address['line1'] ) . "\n";
	echo esc_html( $support_address['line2'] ) . "\n";
	echo esc_html( implode( ' ', [ $support_address['city'], $support_address['state'], $support_address['postal_code'], $support_address['country'] ] ) ) . "\n";
	echo esc_html( implode( ' ', [ $support_phone, $support_email ] ) ) . "\n";
	echo esc_html( gmdate( 'Y-m-d H:iA' ) ) . "\n";
}
