export default function confirmCardPayment( stripe, paymentDetails ) {
	const { redirect } = paymentDetails;

	const partials = redirect.match( /^#wcpay-confirm-pi:(.+):(.+)$/ );
	if ( ! partials ) {
		return { type: 'success' };
	}

	const orderId = partials[ 1 ];
	const clientSecret = partials[ 2 ];

	return stripe.confirmCardPayment( clientSecret )
		.then( ( result ) => {
			const ajaxCall = jQuery.post( wcpay_config.ajaxUrl, {
				action: 'update_order_status',
				// eslint-disable-next-line camelcase
				order_id: orderId,
				// eslint-disable-next-line camelcase
				_ajax_nonce: wcpay_config.updateOrderStatusNonce,
			} );

			return [ ajaxCall, result.error ];
		} )
		.then( async ( [ verificationCall, originalError ] ) => {
			if ( originalError ) {
				throw originalError;
			}

			const response = await verificationCall;
			const result = JSON.parse( response );

			if ( result.error ) {
				throw result.error;
			}

			console.log( result );

			return {
				type: 'success',
				redirectUrl: result.return_url,
			};
		} )
		.catch( ( err ) => {
			console.log( { err } );
			// ToDo: Display the error in the right place somehow.
		} );
}
