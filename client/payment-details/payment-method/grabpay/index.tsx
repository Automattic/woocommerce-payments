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

interface GrabPayDetailsProps {
	charge: Charge;
	isLoading: boolean;
}

const GrabPayDetails: React.FC< GrabPayDetailsProps > = ( {
	charge,
	isLoading,
} ) => {
	const { id, name, email, formattedAddress } = formatPaymentMethodDetails(
		charge
	);

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

export default GrabPayDetails;
