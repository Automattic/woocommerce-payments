<?php
/**
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/email-ipp-receipt-store-details.php
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Payments\Templates\Emails
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;
?>

<div style="margin-bottom: 40px;">
	<table class="td" cellspacing="0" cellpadding="6" style="width: 100%; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;" border="1">
		<tbody>
			<tr>
				<td>
					<h1><?php echo esc_html( $business_name ); ?></h1>
					<?php if ( ! empty( $support_address ) ) { ?>
						<div><?php echo esc_html( $support_address['line1'] ); ?></div>
						<div><?php echo esc_html( $support_address['line2'] ); ?></div>
						<div><?php echo esc_html( implode( ' ', [ $support_address['city'], $support_address['state'], $support_address['postal_code'], $support_address['country'] ] ) ); ?></div>
						<div><?php echo esc_html( implode( ' ', [ $support_phone, $support_email ] ) ); ?></div>
						<div><?php echo esc_html( gmdate( 'Y/m/d - H:iA' ) ); ?></div>
					<?php } ?>
				</td>
			</tr>
		</tbody>
	</table>
</div>
