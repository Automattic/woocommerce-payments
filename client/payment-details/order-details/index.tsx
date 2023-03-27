/**
 * External dependencies
 */
import React, { useEffect } from 'react';

/**
 * Internal dependencies
 */
import { useChargeFromOrder, useLatestFraudOutcome } from '../../data';
import PaymentDetails from '../payment-details';
import { getAdminUrl } from '../../utils';

interface PaymentOrderDetailsProps {
	id: string;
}

const PaymentOrderDetails: React.FC< PaymentOrderDetailsProps > = ( {
	id: orderId,
} ) => {
	const {
		data: fraudOutcome,
		isLoading: isLoadingFraudOutcome,
	} = useLatestFraudOutcome( orderId );

	const { data: charge, isLoading: isLoadingCharge } = useChargeFromOrder(
		orderId
	);

	const shouldRedirect = !! charge.payment_intent;
	const isLoading =
		isLoadingCharge || isLoadingFraudOutcome || shouldRedirect;

	useEffect( () => {
		if ( ! charge ) return;

		if ( shouldRedirect ) {
			const url = getAdminUrl( {
				page: 'wc-admin',
				path: '/payments/transactions/details',
				id: charge.payment_intent,
			} );

			window.location.href = url;
		}
	}, [ charge, shouldRedirect ] );

	return (
		<PaymentDetails
			id={ orderId }
			charge={ charge }
			isLoading={ isLoading }
			fraudOutcome={ fraudOutcome }
			showTimeline={ ! shouldRedirect }
		/>
	);
};

export default PaymentOrderDetails;
