/**
 * External dependencies
 */
import apiFetch from '@wordpress/api-fetch';

export const makeTosAcceptanceRequest = async ( { accept } ) =>
	apiFetch( {
		path: '/wc/v3/payments/tos',
		method: 'POST',
		data: { accept },
	} );

export const enableGatewayAfterTosDecline = async () =>
	apiFetch( {
		path: '/wc/v3/payments/tos/reactivate',
		method: 'POST',
	} );
