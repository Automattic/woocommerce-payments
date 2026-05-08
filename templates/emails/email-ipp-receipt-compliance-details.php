<?php
/**
 * IPP Receipt Compliance Details
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/email-ipp-receipt-compliance-details.php.
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

$payment_method_brand_display_name = $payment_method_brand_display_name ?? ucfirst( $payment_method_details['brand'] ?? '' );
?>

<div style="margin-bottom: 40px;">
	<table class="td" cellspacing="0" cellpadding="6" style="width: 100%; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;" border="1">
		<tbody>
			<tr>
				<th class="td" scope="row" colspan="2">
					<?php esc_html_e( 'Payment Method', 'woocommerce-payments' ); ?>
				</th>
				<td class="td">
					<div>
						<?php if ( ! empty( $payment_method_brand_image_url ) ) : ?>
							<img src="<?php echo esc_url( $payment_method_brand_image_url ); ?>" alt="<?php echo esc_attr( $payment_method_brand_display_name ); ?>" width="38" height="24" style="display: inline-block; max-width: 38px; max-height: 24px; margin-right: 8px; vertical-align: middle;" />
						<?php endif; ?>
						<span style="vertical-align: middle;"><?php echo esc_html( sprintf( '%s - %s', $payment_method_brand_display_name, $payment_method_details['last4'] ) ); ?></span>
					</div>
				</td>
			</tr>
			<tr>
				<th class="td" scope="row" colspan="2">
					<?php esc_html_e( 'Application Name', 'woocommerce-payments' ); ?>
				</th>
				<td class="td">
					<div id="application-preferred-name"><?php echo esc_html( ucfirst( $receipt['application_preferred_name'] ) ); ?></div>
				</td>
			</tr>
			<tr>
				<th class="td" scope="row" colspan="2">
					<?php esc_html_e( 'AID', 'woocommerce-payments' ); ?>
				</th>
				<td class="td">
					<div id="dedicated-file-name"><?php echo esc_html( ucfirst( $receipt['dedicated_file_name'] ) ); ?></div>
				</td>
			</tr>
			<tr>
				<th class="td" scope="row" colspan="2">
					<?php esc_html_e( 'Account Type', 'woocommerce-payments' ); ?>
				</th>
				<td class="td">
					<div id="account-type"><?php echo esc_html( ucfirst( $receipt['account_type'] ) ); ?></div>
				</td>
			</tr>
		</tbody>
	</table>
</div>
