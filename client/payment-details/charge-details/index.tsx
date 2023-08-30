/**
 * External dependencies
 */
import React, { useEffect } from 'react';
import { getHistory } from '@woocommerce/navigation';

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
import { getIsChargeId, usePaymentIntentWithChargeFallback } from '../../data';
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

	const isChargeId = getIsChargeId( id );
	const isLoading = isChargeId || isLoadingData;

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

			getHistory().replace( url );
		}
	}, [ data, isChargeId ] );

	return (
		<PaymentDetails
			id={ id }
			error={ error }
			charge={ charge }
			metadata={ metadata }
			isLoading={ isLoading }
			showTimeline={ ! isChargeId }
			paymentIntent={ paymentIntent }
		/>
	);
};

export default PaymentChargeDetails;
