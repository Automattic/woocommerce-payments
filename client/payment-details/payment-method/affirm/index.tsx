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

interface PaymentMethodDetails {
	id: string;
	name: string;
	email: string;
	formattedAddress: string;
}

interface PaymentMethodPlaceholders {
	id: string;
	name: string;
	email: string;
	formattedAddress: string;
}

/**
 * Placeholders to display while loading.
 */
const paymentMethodPlaceholders: PaymentMethodPlaceholders = {
	id: 'id placeholder',
	name: 'name placeholder',
	email: 'email placeholder',
	formattedAddress: 'address placeholder',
};

interface ExternalCharge {
	billing_details: {
		name: string;
		email: string;
		formatted_address: string;
	};
	payment_method: string;
	payment_method_details?: any;
}

/**
 * Extracts and formats payment method details from a charge.
 *
 * @param {ExternalCharge} charge The charge object.
 * @return {PaymentMethodDetails} A flat hash of all necessary values.
 */
const formatPaymentMethodDetails = (
	charge: ExternalCharge
): PaymentMethodDetails => {
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
 * Props interface for AffirmDetails component
 */
interface AffirmDetailsProps {
	charge?: ExternalCharge;
	isLoading: boolean;
}

const AffirmDetails: React.FC< AffirmDetailsProps > = ( {
	charge,
	isLoading,
} ) => {
	const details: PaymentMethodDetails | PaymentMethodPlaceholders =
		charge && charge.payment_method_details
			? formatPaymentMethodDetails( charge )
			: paymentMethodPlaceholders;

	const {
		id,
		name,
		email,
		formattedAddress,
	}: PaymentMethodDetails | PaymentMethodPlaceholders = details;

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
