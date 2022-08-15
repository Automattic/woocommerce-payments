/** @format */
/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';
import { Charge } from '../../types/charges';
import { PaymentIntent } from '../../types/payment-intents';
import { getChargeData } from '../charges';
import { ChargeResponse } from '../charges/types';
import { STORE_NAME } from '../constants';

export const getIsChargeId = ( id: string ): boolean =>
	-1 !== id.indexOf( 'ch_' );

export const usePaymentIntentWithChargeFallback = (
	id: string
): ChargeResponse =>
	useSelect(
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
			} = selectors;

			const paymentIntent: PaymentIntent = getPaymentIntent( id );

			return {
				data: paymentIntent?.charge || ( {} as Charge ),
				error: getPaymentIntentError( id ),
				isLoading: isResolving( 'getPaymentIntent', [ id ] ),
			};
		},
		[ id ]
	);
