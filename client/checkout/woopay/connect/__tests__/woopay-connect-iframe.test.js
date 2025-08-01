/**
 * External dependencies
 */
import React from 'react';
import { render, waitFor } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { WooPayConnectIframe } from '../woopay-connect-iframe';
import { getConfig } from 'wcpay/utils/checkout';
import { getTracksIdentity } from 'tracks';
import {
	INJECTED_STATE,
	setConnectIframeInjectedState,
} from 'wcpay/checkout/woopay/connect/connect-utils';

jest.mock( 'wcpay/utils/checkout', () => ( {
	getConfig: jest.fn(),
} ) );

jest.mock( 'tracks', () => ( {
	getTracksIdentity: jest.fn(),
} ) );

jest.mock( 'wcpay/checkout/woopay/connect/connect-utils', () => ( {
	setConnectIframeInjectedState: jest.fn(),
	INJECTED_STATE: { INJECTED: 'INJECTED' },
} ) );

describe( 'WooPayConnectIframe', () => {
	const mockTestMode = '1';
	const mockWoopayHost = 'https://woopay.test';
	const mockTracksUserId = '123';

	beforeEach( () => {
		getConfig.mockImplementation( ( key ) => {
			if ( key === 'testMode' ) return mockTestMode;
			if ( key === 'woopayHost' ) return mockWoopayHost;
		} );

		getTracksIdentity.mockResolvedValue( mockTracksUserId );

		jest.clearAllMocks();
	} );

	it( 'fetches configuration and sets iframe URL on mount', async () => {
		const { container } = render( <WooPayConnectIframe /> );

		const iframe = container.querySelector( 'iframe' );
		await waitFor( () => {
			expect( iframe.src ).toContain( mockWoopayHost );
			expect( iframe.src ).toContain( `testMode=${ mockTestMode }` );
			expect( iframe.src ).toContain(
				`tracksUserIdentity=${ mockTracksUserId }`
			);
		} );
	} );

	it( 'sets up "postMessage" with success action on iframe load', async () => {
		window.dispatchEvent = jest.fn();

		let loadEventCallback;
		const mockAddEventListener = jest.fn( ( event, callback ) => {
			if ( event === 'load' ) {
				loadEventCallback = callback;
			}
		} );

		jest.spyOn(
			HTMLIFrameElement.prototype,
			'addEventListener'
		).mockImplementation( mockAddEventListener );

		render( <WooPayConnectIframe /> );

		// Simulate iframe load.
		loadEventCallback();

		await waitFor( () => {
			expect( mockAddEventListener ).toHaveBeenCalledWith(
				'load',
				expect.any( Function )
			);
			expect( setConnectIframeInjectedState ).toHaveBeenCalledWith(
				INJECTED_STATE.INJECTED
			);
			const messageEvent = window.dispatchEvent.mock.calls[ 0 ][ 0 ];
			expect( messageEvent.data.action ).toBe(
				'get_iframe_post_message_success'
			);
		} );
	} );
} );
