/**
 * External dependencies
 */
import FingerprintJS from '@fingerprintjs/fingerprintjs';
import { useEffect, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import confirmCardPayment from './confirm-card-payment.js';

export const usePaymentCompleteHandler = (
	api,
	stripe,
	elements,
	onCheckoutAfterProcessingWithSuccess,
	emitResponse,
	shouldSavePayment
) => {
	// Once the server has completed payment processing, confirm the intent of necessary.
	useEffect(
		() =>
			onCheckoutAfterProcessingWithSuccess(
				( { processingResponse: { paymentDetails } } ) =>
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

export const useFingerprint = () => {
	const [ fingerprint, setFingerprint ] = useState( '' );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		const getFingerprint = async () => {
			try {
				const fingerprintPublicAgent = await FingerprintJS.load( {
					monitoring: false,
				} );

				// Do not mount element if fingerprinting is not available
				if ( ! fingerprintPublicAgent ) {
					throw new Error( 'Unable to generate a fingerprint' );
				}

				const { visitorId } = await fingerprintPublicAgent.get();
				setFingerprint( visitorId );
			} catch ( err ) {
				console.log( { err } );
				setError( err );
			}
		};

		getFingerprint();
	}, [] );

	return [ fingerprint, error ];
};
