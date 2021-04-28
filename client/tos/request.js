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
export const maybeTrackKycCompleted = async () => {
	// eslint-disable-next-line camelcase
	const trackKycCompleted = wcpay_tos_settings.trackKycCompleted;
	if ( ! wcpayTracks.isEnabled || ! trackKycCompleted ) {
		return;
	}

	wcpayTracks.recordEvent( wcpayTracks.events.CONNECT_ACCOUNT_KYC_COMPLETED, {
		// eslint-disable-next-line camelcase
		is_existing_stripe_account:
			trackKycCompleted.is_existing_stripe_account,
	} );

	apiFetch( {
		path: '/wc/v3/payments/tos/kyc_track_completed',
		method: 'POST',
	} );
};
