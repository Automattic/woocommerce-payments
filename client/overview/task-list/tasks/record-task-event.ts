/** @format **/

export const recordTaskEvent = (
	eventName: string,
	eventProperties: Record< string, unknown > = {}
): void => {
	if ( window.wcpaySettings ) {
		Object.assign( eventProperties, {
			is_test_mode: wcpaySettings.testMode,
			jetpack_connected: wcpaySettings.isJetpackConnected,
			wcpay_version: wcpaySettings.version,
			woo_country_code: wcpaySettings.connect.country,
			hosting_provider: wcpaySettings.trackingInfo?.hosting_provider,
		} );

		for ( const key in eventProperties ) {
			if ( eventProperties[ key ] === undefined ) {
				delete eventProperties[ key ];
			}
		}
	}

	const recordFunction = wc?.tracks?.recordEvent ?? wcTracks.recordEvent;
	recordFunction( eventName, eventProperties );
};
