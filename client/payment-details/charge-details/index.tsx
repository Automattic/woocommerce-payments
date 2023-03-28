/**
 * External dependencies
 */
import React, { useEffect } from 'react';

/**
 * Internal dependencies
 */
import PaymentDetails from '../payment-details';
import {
	PaymentChargeDetailsProps,
	PaymentChargeDetailsResponse,
	isCharge,
	isPaymentIntent,
} from '../types';
import {
	getIsChargeId,
	useLatestFraudOutcome,
	usePaymentIntentWithChargeFallback,
} from '../../data';
import { PaymentIntent } from '../../types/payment-intents';
import { Charge } from '../../types/charges';
import { getAdminUrl } from '../../utils';

const PaymentChargeDetails: React.FC< PaymentChargeDetailsProps > = ( {
	id,
} ) => {
	const {
		data,
		error,
		isLoading: isLoadingData,
	} = usePaymentIntentWithChargeFallback(
		id
	) as PaymentChargeDetailsResponse;

	const paymentIntent = isPaymentIntent( data )
		? data
		: ( {} as PaymentIntent );

	const orderId = paymentIntent?.metadata?.order_id;

	const {
		data: fraudOutcome,
		isLoading: isLoadingFraudOutcome,
	} = useLatestFraudOutcome( orderId );

	const isChargeId = getIsChargeId( id );
	const isLoading = isChargeId || isLoadingData || isLoadingFraudOutcome;

	const charge =
		( isPaymentIntent( data ) ? data.charge : data ) || ( {} as Charge );
	const metadata = isPaymentIntent( data ) ? data.metadata : {};

	useEffect( () => {
		if ( ! isCharge( data ) ) {
			return;
		}

		const shouldRedirect = !! ( isChargeId && data.payment_intent );

		if ( shouldRedirect ) {
			const url = getAdminUrl( {
				page: 'wc-admin',
				path: '/payments/transactions/details',
				id: data.payment_intent,
			} );

			window.location.href = url;
		}
	}, [ data, isChargeId ] );

	return (
		<PaymentDetails
			id={ id }
			error={ error }
			charge={ charge }
			metadata={ metadata }
			isLoading={ isLoading }
			fraudOutcome={ fraudOutcome }
			showTimeline={ ! isChargeId }
			paymentIntent={ paymentIntent }
		/>
	);
};

export default PaymentChargeDetails;
