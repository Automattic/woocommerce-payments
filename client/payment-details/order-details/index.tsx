/**
 * External dependencies
 */
import React, { useEffect } from 'react';

/**
 * Internal dependencies
 */
import { useChargeFromOrder } from '../../data';
import PaymentDetails from '../payment-details';
import { getAdminUrl } from '../../utils';

interface PaymentOrderDetailsProps {
	id: string;
}

const PaymentOrderDetails: React.FC< PaymentOrderDetailsProps > = ( {
	id: orderId,
} ) => {
	const { data: charge, isLoading: isLoadingCharge } = useChargeFromOrder(
		orderId
	);

	const shouldRedirect = !! charge.payment_intent;
	const isLoading = isLoadingCharge || shouldRedirect;

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
			showTimeline={ ! shouldRedirect }
		/>
	);
};

export default PaymentOrderDetails;
