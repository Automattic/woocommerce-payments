/** @format */
/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';
import { getAdminUrl } from 'wcpay/utils';
import { STORE_NAME } from '../constants';

const getIsChargeId = ( id ) => -1 !== id.indexOf( 'ch_' );

const getChargeData = (
	chargeId,
	{ getCharge, isResolving, getChargeError },
	isChargeId = false
) => {
	const data = getCharge( chargeId );
	const shouldRedirect = !! ( isChargeId && data.payment_intent );

	const redirect = shouldRedirect && {
		url: getAdminUrl( {
			page: 'wc-admin',
			path: '/payments/transactions/details',
			id: data.payment_intent,
		} ),
	};

	const isLoading =
		isResolving( 'getCharge', [ chargeId ] ) || shouldRedirect;

	return {
		data,
		redirect,
		isLoading,
		error: getChargeError( chargeId ),
	};
};

export const usePaymentIntentFallback = ( id ) =>
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

			const paymentIntent = getPaymentIntent( id );

			if ( paymentIntent?.charge?.id ) {
				const { id: paymentIntentId } = paymentIntent.charge;

				return getChargeData( paymentIntentId, selectors );
			}

			return {
				data: {},
				error: getPaymentIntentError( id ),
				isLoading: isResolving( 'getPaymentIntent', [ id ] ),
			};
		},
		[ id ]
	);
