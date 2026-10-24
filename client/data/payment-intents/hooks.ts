/** @format */
/**
 * External dependencies
 */
import { useDispatch, useSelect } from '@wordpress/data';
import { PaymentIntent } from '../../types/payment-intents';
import { getChargeData } from '../charges';
// The charge-id fallback reads charge state, which now lives in its own store.
import { store as chargesStore } from '../charges/store';
import { PaymentChargeDetailsResponse } from '../../payment-details/types';
import { store as paymentIntentsStore } from './store';
import { Charge } from 'wcpay/types/charges';

export const getIsChargeId = ( id: string ): boolean =>
	id.indexOf( 'ch_' ) !== -1 || id.indexOf( 'py_' ) !== -1;

export const usePaymentIntentWithChargeFallback = (
	id: string
): PaymentChargeDetailsResponse => {
	const { data, error, isLoading } = useSelect(
		( select ) => {
			const isChargeId = getIsChargeId( id );

			/**
			 * Charge ID fallback flow
			 *
			 * It should redirect from "ch_" ID to the equivalent "pi_" ID
			 */
			if ( isChargeId ) {
				return getChargeData( id, select( chargesStore ) );
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
			} = select( paymentIntentsStore );

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

	const { refundCharge } = useDispatch( paymentIntentsStore );

	const doRefund = ( charge: Charge, reason: string | null ) =>
		refundCharge( charge, reason );

	return {
		data,
		error,
		isLoading,
		doRefund,
	};
};
