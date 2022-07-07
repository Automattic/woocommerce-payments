/** @format */
/**
 * External dependencies
 */
import { SelectorMap, useSelect } from '@wordpress/data';
import { getAdminUrl } from 'wcpay/utils';
import { Charge } from '../../types/charges';
import { ApiError } from '../../types/errors';
import { PaymentIntent } from '../../types/payment-intents';
import { STORE_NAME } from '../constants';
import { PaymentIntentionFallbackResponse } from './types';

const getIsChargeId = ( id: string ): boolean => -1 !== id.indexOf( 'ch_' );

const getChargeData = (
	chargeId: string,
	{ getCharge, isResolving, getChargeError }: SelectorMap,
	isChargeId = false
) => {
	const data: Charge = getCharge( chargeId );
	const shouldRedirect = !! ( isChargeId && data.payment_intent );

	const redirect = shouldRedirect
		? {
				url: getAdminUrl( {
					page: 'wc-admin',
					path: '/payments/transactions/details',
					id: data.payment_intent,
				} ),
		  }
		: undefined;

	const isLoading: boolean =
		isResolving( 'getCharge', [ chargeId ] ) || shouldRedirect;

	return {
		data,
		redirect,
		isLoading,
		error: getChargeError( chargeId ) as ApiError,
	};
};

export const usePaymentIntentFallback = (
	id: string
): PaymentIntentionFallbackResponse =>
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
				return getChargeData( id, selectors, isChargeId );
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

			if ( paymentIntent?.charge?.id ) {
				const { id: chargeId } = paymentIntent.charge;

				return getChargeData( chargeId, selectors );
			}

			return {
				data: {} as Charge,
				error: getPaymentIntentError( id ),
				isLoading: isResolving( 'getPaymentIntent', [ id ] ),
			};
		},
		[ id ]
	);
