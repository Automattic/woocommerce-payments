/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import Detail from '../detail';

/**
 * Extracts and formats payment method details from a charge.
 *
 * @param {Object} charge The charge object.
 * @return {Object}       A flat hash of all necessary values.
 */
const formatPaymentMethodDetails = ( charge ) => {
	const { billing_details: billingDetails, payment_method: id } = charge;

	const { name, email, formatted_address: formattedAddress } = billingDetails;

	return {
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
	id: 'id placeholder',
	name: 'name placeholder',
	email: 'email placeholder',
	formattedAddress: 'address placeholder',
};

const CardDetails = ( { charge = {}, isLoading } ) => {
	const details =
		charge && charge.payment_method_details
			? formatPaymentMethodDetails( charge )
			: paymentMethodPlaceholders;

	const { id, name, email, formattedAddress } = details;

	return (
		<div className="payment-method-details">
			<div className="payment-method-details__column">
				<Detail
					isLoading={ isLoading }
					label={ __( 'ID', 'woocommerce-payments' ) }
				>
					{ !! id ? id : '–' }
				</Detail>
			</div>

			<div className="payment-method-details__column">
				<Detail
					isLoading={ isLoading }
					label={ __( 'Owner', 'woocommerce-payments' ) }
				>
					{ name || '–' }
				</Detail>

				<Detail
					isLoading={ isLoading }
					label={ __( 'Owner email', 'woocommerce-payments' ) }
				>
					{ email || '–' }
				</Detail>

				<Detail
					isLoading={ isLoading }
					label={ __( 'Address', 'woocommerce-payments' ) }
				>
					<span
						dangerouslySetInnerHTML={ {
							__html: formattedAddress || '–',
						} }
					/>
				</Detail>
			</div>
		</div>
	);
};

export default CardDetails;
