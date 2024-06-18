/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import React from 'react';

/**
 * Internal dependencies.
 */
import Detail from '../detail';
import { PaymentMethodDetails } from 'wcpay/payment-details/types';
import { Charge } from 'wcpay/types/charges';

/**
 * Extracts and formats payment method details from a charge.
 *
 * @param {Charge} charge The charge object.
 * @return {PaymentMethodDetails} A flat hash of all necessary values.
 */
const formatPaymentMethodDetails = ( charge: Charge ): PaymentMethodDetails => {
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
const paymentMethodPlaceholders: PaymentMethodDetails = {
	id: 'id placeholder',
	name: 'name placeholder',
	email: 'email placeholder',
	formattedAddress: 'address placeholder',
};

/**
 * Props interface for AffirmDetails component
 */
interface AffirmDetailsProps {
	charge?: Charge;
	isLoading: boolean;
}

const AffirmDetails: React.FC< AffirmDetailsProps > = ( {
	charge,
	isLoading,
} ) => {
	const details: PaymentMethodDetails | typeof paymentMethodPlaceholders =
		charge && charge.payment_method_details
			? formatPaymentMethodDetails( charge )
			: paymentMethodPlaceholders;

	const {
		id,
		name,
		email,
		formattedAddress,
	}: PaymentMethodDetails | typeof paymentMethodPlaceholders = details;

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
						// eslint-disable-next-line react/no-danger
						dangerouslySetInnerHTML={ {
							__html: formattedAddress || '–',
						} }
					/>
				</Detail>
			</div>
		</div>
	);
};

export default AffirmDetails;
