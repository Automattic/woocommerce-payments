/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import PaymentDetailsPaymentMethodDetail from '../detail';

/**
 * Extracts and formats payment method details from a charge.
 *
 * @param {Object} charge The charge object.
 * @return {Object}       A flat hash of all necessary values.
 */
const formatPaymentMethodDetails = ( charge ) => {
	const { billing_details: billingDetails, payment_method: id } = charge;
	const {
		bsb_number: bsb,
		last4,
		fingerprint,
	} = charge.payment_method_details.au_becs_debit;

	const { name, email, formatted_address: formattedAddress } = billingDetails;

	return {
		bsb,
		last4,
		fingerprint,
		id,
		name,
		email,
		formattedAddress,
	};
};

/**
 * Placeholders to display while loading.
 */
const paymentMethodPlaceholders = {
	bsb: '000000',
	last4: '0000',
	fingerprint: 'fingerprint placeholder',
	id: 'id placeholder',
	name: 'name placeholder',
	email: 'email placeholder',
	formattedAddress: 'address placeholder',
};

const BecsDetails = ( { charge = {}, isLoading } ) => {
	const details =
		charge && charge.payment_method_details
			? formatPaymentMethodDetails( charge )
			: paymentMethodPlaceholders;

	const { bsb, last4, id, name, email, formattedAddress } = details;

	// Shorthand for more readable code.
	const Detail = PaymentDetailsPaymentMethodDetail;

	return (
		<div className="payment-method-details">
			<div className="payment-method-details__column">
				<Detail
					isLoading={ isLoading }
					label={ __( 'BSB', 'woocommerce-payments' ) }
				>
					{ bsb }
				</Detail>

				<Detail
					isLoading={ isLoading }
					label={ __( 'Account', 'woocommerce-payments' ) }
				>
					&bull;&bull;&bull;&bull;&bull;&nbsp;{ last4 }
				</Detail>

				<Detail
					isLoading={ isLoading }
					label={ __( 'ID', 'woocommerce-payments' ) }
				>
					{ id }
				</Detail>
			</div>

			<div className="payment-method-details__column">
				<Detail
					isLoading={ isLoading }
					label={ __( 'Owner', 'woocommerce-payments' ) }
				>
					{ name }
				</Detail>

				<Detail
					isLoading={ isLoading }
					label={ __( 'Owner email', 'woocommerce-payments' ) }
				>
					{ email }
				</Detail>

				<Detail
					isLoading={ isLoading }
					label={ __( 'Address', 'woocommerce-payments' ) }
				>
					<span
						dangerouslySetInnerHTML={ {
							__html: formattedAddress,
						} }
					/>
				</Detail>
			</div>
		</div>
	);
};

export default BecsDetails;
