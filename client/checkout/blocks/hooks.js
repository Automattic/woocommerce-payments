/**
 * External dependencies
 */
import { useEffect, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import confirmCardPayment from './confirm-card-payment.js';
import {
	getFingerprint,
	FINGERPRINT_GENERIC_ERROR,
} from '../utils/fingerprint.js';

export const usePaymentCompleteHandler = (
	api,
	stripe,
	elements,
	onCheckoutSuccess,
	emitResponse,
	shouldSavePayment
) => {
	// Once the server has completed payment processing, confirm the intent of necessary.
	useEffect(
		() =>
			onCheckoutSuccess( ( { processingResponse: { paymentDetails } } ) =>
				confirmCardPayment(
					api,
					paymentDetails,
					emitResponse,
					shouldSavePayment
				)
			),
		// not sure if we need to disable this, but kept it as-is to ensure nothing breaks. Please consider passing all the deps.
		// eslint-disable-next-line react-hooks/exhaustive-deps
		[ elements, stripe, api, shouldSavePayment ]
	);
};

/**
 * Handles the Block Checkout onCheckoutFail event.
 *
 * Displays the error message returned from server in the paymentDetails object in the PAYMENTS notice context container.
 *
 * @param {*} onCheckoutFail The onCheckoutFail event.
 * @param {*} emitResponse   Various helpers for usage with observer.
 */
export const usePaymentFailHandler = ( onCheckoutFail, emitResponse ) => {
	useEffect(
		() =>
			onCheckoutFail( ( { processingResponse: { paymentDetails } } ) => {
				return {
					type: 'failure',
					message: paymentDetails.errorMessage,
					messageContext: emitResponse.noticeContexts.PAYMENTS,
				};
			} ),
		[ onCheckoutFail, emitResponse.noticeContexts.PAYMENTS ]
	);
};

export const useFingerprint = () => {
	const [ fingerprint, setFingerprint ] = useState( '' );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		( async () => {
			try {
				const { visitorId } = await getFingerprint();
				setFingerprint( visitorId );
			} catch ( err ) {
				setError(
					err.message ? err.message : FINGERPRINT_GENERIC_ERROR
				);
			}
		} )();
	}, [] );

	return [ fingerprint, error ];
};
