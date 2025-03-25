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
?>

<div style="margin-bottom: 40px;">
	<table class="td" cellspacing="0" cellpadding="6" style="width: 100%; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;" border="1">
		<tbody>
			<tr>
				<th class="td" scope="row" colspan="2">
					<?php esc_html_e( 'Payment Method', 'woocommerce-payments' ); ?>
				</th>
				<td class="td">
					<div><?php echo esc_html( sprintf( '%s - %s', ucfirst( $payment_method_details['brand'] ), $payment_method_details['last4'] ) ); ?></div>
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
