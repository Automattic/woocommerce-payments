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
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Payments\Templates\Emails
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;
?>

<div style="margin-bottom: 24px; padding-bottom: 24px; border-bottom: 1px solid #e5e5e5; border-top: 1px solid #e5e5e5; padding-top: 24px;">
	<h2 style="display: block; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; font-size: 18px; font-weight: bold; line-height: 130%; margin: 0 0 18px; text-align: left;" class="woocommerce-email-store-details__heading">
		<?php esc_html_e( 'Store Details', 'woocommerce-payments' ); ?>
	</h2>
	<table class="td" cellspacing="0" cellpadding="6" style="width: 100%; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;" border="1">
		<tbody>
			<tr>
				<th class="td" scope="row" style="text-align: left; width: 30%;">
					<?php esc_html_e( 'Store Name:', 'woocommerce-payments' ); ?>
				</th>
				<td class="td" style="text-align: left;">
					<?php echo esc_html( $business_name ); ?>
				</td>
			</tr>
			<?php if ( ! empty( $support_address ) && is_array( $support_address ) ) : ?>
				<tr>
					<th class="td" scope="row" style="text-align: left;">
						<?php esc_html_e( 'Address:', 'woocommerce-payments' ); ?>
					</th>
					<td class="td" style="text-align: left;">
						<?php
						$address_parts = [];
						if ( ! empty( $support_address['line1'] ) ) {
							$address_parts[] = esc_html( $support_address['line1'] );
						}
						if ( ! empty( $support_address['line2'] ) ) {
							$address_parts[] = esc_html( $support_address['line2'] );
						}
						if ( ! empty( $support_address['city'] ) || ! empty( $support_address['state'] ) || ! empty( $support_address['postal_code'] ) ) {
							$city_state_zip = [];
							if ( ! empty( $support_address['city'] ) ) {
								$city_state_zip[] = esc_html( $support_address['city'] );
							}
							if ( ! empty( $support_address['state'] ) ) {
								$city_state_zip[] = esc_html( $support_address['state'] );
							}
							if ( ! empty( $support_address['postal_code'] ) ) {
								$city_state_zip[] = esc_html( $support_address['postal_code'] );
							}
							if ( ! empty( $city_state_zip ) ) {
								$address_parts[] = implode( ', ', $city_state_zip );
							}
						}
						if ( ! empty( $support_address['country'] ) ) {
							$address_parts[] = esc_html( $support_address['country'] );
						}
						echo wp_kses_post( implode( '<br>', $address_parts ) );
						?>
					</td>
				</tr>
			<?php endif; ?>
			<?php if ( ! empty( $support_phone ) || ! empty( $support_email ) ) : ?>
				<tr>
					<th class="td" scope="row" style="text-align: left;">
						<?php esc_html_e( 'Contact:', 'woocommerce-payments' ); ?>
					</th>
					<td class="td" style="text-align: left;">
						<?php
						$contact_parts = [];
						if ( ! empty( $support_phone ) ) {
							$contact_parts[] = esc_html( $support_phone );
						}
						if ( ! empty( $support_email ) ) {
							$contact_parts[] = esc_html( $support_email );
						}
						echo wp_kses_post( implode( '<br>', $contact_parts ) );
						?>
					</td>
				</tr>
			<?php endif; ?>
			<tr>
				<th class="td" scope="row" style="text-align: left;">
					<?php esc_html_e( 'Date:', 'woocommerce-payments' ); ?>
				</th>
				<td class="td" style="text-align: left;">
					<?php echo esc_html( gmdate( 'Y-m-d H:iA' ) ); ?>
				</td>
			</tr>
		</tbody>
	</table>
</div>
