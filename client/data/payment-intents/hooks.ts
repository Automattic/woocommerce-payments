/** @format */
/**
 * External dependencies
 */
import { useDispatch, useSelect } from '@wordpress/data';
import { PaymentIntent } from '../../types/payment-intents';
import { getChargeData } from '../charges';
import { PaymentChargeDetailsResponse } from '../../payment-details/types';
import { STORE_NAME } from '../constants';
import { Charge } from 'wcpay/types/charges';

export const getIsChargeId = ( id: string ): boolean =>
	-1 !== id.indexOf( 'ch_' ) || -1 !== id.indexOf( 'py_' );

export const usePaymentIntentWithChargeFallback = (
	id: string
): PaymentChargeDetailsResponse => {
	const { data, error, isLoading } = useSelect(
		( select ) => {
			const selectors = select( STORE_NAME );
			const isChargeId = getIsChargeId( id );

			/**
			 * Charge ID fallback flow
			 *
			 * It should redirect from "ch_" ID to the equivalent "pi_" ID
			 */
			if ( isChargeId ) {
				return getChargeData( id, selectors );
			}

			/**
			 * Default payment intent flow
			 *
			 * Fetch the payment intent and use it to fetch the charge data
			 */
			const {
				getPaymentIntent,
				getPaymentIntentError,
				isResolving,
				hasFinishedResolution,
			} = selectors;

			const paymentIntent: PaymentIntent = getPaymentIntent( id );

			return {
				data: paymentIntent || ( {} as PaymentIntent ),
				error: getPaymentIntentError( id ),
				isLoading:
					isResolving( 'getPaymentIntent', [ id ] ) ||
					! hasFinishedResolution( 'getPaymentIntent', [ id ] ),
			};
		},
		[ id ]
	);

	const { refundCharge } = useDispatch( STORE_NAME );

	const doRefund = ( charge: Charge, reason: string | null ) =>
		refundCharge( charge, reason );

	return {
		data,
		error,
		isLoading,
		doRefund,
	};
};
