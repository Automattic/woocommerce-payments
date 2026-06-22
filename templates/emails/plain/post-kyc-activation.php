<?php
/**
 * Post-KYC activation reminder email (plain text)
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/plain/post-kyc-activation.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Payments\Templates\Emails\Plain
 * @version 10.8.0
 *
 * @var int    $stage
 * @var string $email_heading
 * @var string $additional_content
 * @var string $cta_url
 * @var string $cta_label
 */

defined( 'ABSPATH' ) || exit;

$stage_copy = [
	7  => [
		'heading' => __( 'Your store is ready — let’s make your first sale', 'woocommerce-payments' ),
		'body'    => __( 'Your WooPayments account is approved and ready to accept payments. Now it’s about getting eyes on your store — share your link, tell your network, and make your first sale.', 'woocommerce-payments' ),
	],
	14 => [
		'heading' => __( 'Two weeks in — have you shared your store yet?', 'woocommerce-payments' ),
		'body'    => __( 'Your account is fully approved and accepting payments. Share your store with your first potential customers to get that first sale.', 'woocommerce-payments' ),
	],
	30 => [
		'heading' => __( 'Your payments are ready — your first sale can be too', 'woocommerce-payments' ),
		'body'    => __( 'Everything on the payments side is ready. The next step is getting your first customer through the door — share your store link and start spreading the word.', 'woocommerce-payments' ),
	],
];

$content = $stage_copy[ $stage ] ?? $stage_copy[7];

echo esc_html( wp_strip_all_tags( $content['heading'] ) ) . "\n\n";
echo "=====================================================\n\n";
echo esc_html( $content['body'] ) . "\n\n";

echo esc_html( $cta_label ) . ': ' . esc_url_raw( $cta_url ) . "\n\n";

if ( $additional_content ) {
	echo "-----------------------------------------------------\n\n";
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) ) . "\n\n";
}

echo "\n=====================================================\n\n";
echo esc_html( wp_strip_all_tags( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ) ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment -- WooCommerce core hook, not defined by WooPayments.
