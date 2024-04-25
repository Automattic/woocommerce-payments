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
		last4,
		fingerprint,
		country: countryCode,
	} = charge.payment_method_details.sepa_debit;

	const { name, email, formatted_address: formattedAddress } = billingDetails;
	// Use the full country name.
	const country = wcSettings.countries[ countryCode ];

	return {
		last4,
		fingerprint,
		id,
		name,
		email,
		country,
		formattedAddress,
	};
};

/**
 * Placeholders to display while loading.
 */
const paymentMethodPlaceholders = {
	last4: '0000',
	fingerprint: __( 'fingerprint placeholder', 'woocommerce-payments' ),
	id: __( 'id placeholder', 'woocommerce-payments' ),
	name: __( 'name placeholder', 'woocommerce-payments' ),
	email: __( 'email placeholder', 'woocommerce-payments' ),
	formattedAddress: __( 'address placeholder', 'woocommerce-payments' ),
	country: __( 'country placeholder', 'woocommerce-payments' ),
};

const SepaDetails = ( { charge = {}, isLoading } ) => {
	const details =
		charge && charge.payment_method_details
			? formatPaymentMethodDetails( charge )
			: paymentMethodPlaceholders;

	const { last4, id, name, email, country, formattedAddress } = details;

	// Shorthand for more readable code.
	const Detail = PaymentDetailsPaymentMethodDetail;

	return (
		<div className="payment-method-details">
			<div className="payment-method-details__column">
				<Detail
					isLoading={ isLoading }
					label={ __( 'IBAN', 'woocommerce-payments' ) }
				>
					&bull;&bull;&bull;&bull;&nbsp;{ last4 }
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

				<Detail
					isLoading={ isLoading }
					label={ __( 'Origin', 'woocommerce-payments' ) }
				>
					{ country }
				</Detail>
			</div>
		</div>
	);
};

export default SepaDetails;
