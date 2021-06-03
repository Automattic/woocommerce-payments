/* global wcpay_tos_settings */

/**
 * External dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import wcpayTracks from 'tracks';

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

/**
 * Records track if we're able to and send an API request to delete the option
 * that triggers this track.
 */
export const maybeTrackStripeConnected = async () => {
	// eslint-disable-next-line camelcase
	const trackStripeConnected = wcpay_tos_settings.trackStripeConnected;
	if ( ! wcpayTracks.isEnabled() || ! trackStripeConnected ) {
		return;
	}

	wcpayTracks.recordEvent(
		wcpayTracks.events.CONNECT_ACCOUNT_STRIPE_CONNECTED,
		{
			// eslint-disable-next-line camelcase
			is_existing_stripe_account:
				trackStripeConnected.is_existing_stripe_account,
		}
	);

	apiFetch( {
		path: '/wc/v3/payments/tos/stripe_track_connected',
		method: 'POST',
	} );
};
