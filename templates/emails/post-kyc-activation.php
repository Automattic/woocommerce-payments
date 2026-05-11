<?php
/**
 * Post-KYC activation email (HTML).
 *
 * @package WooCommerce\Payments\Templates\Emails
 *
 * @var int    $stage
 * @var string $email_heading
 * @var string $additional_content
 * @var bool   $sent_to_admin
 * @var bool   $plain_text
 * @var WC_Email $email
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

do_action( 'woocommerce_email_header', $content['heading'], $email ); ?>

<p><?php echo esc_html( $content['body'] ); ?></p>

<?php if ( $additional_content ) : ?>
	<p><?php echo wp_kses_post( wptexturize( $additional_content ) ); ?></p>
<?php endif; ?>

<?php do_action( 'woocommerce_email_footer', $email ); ?>
