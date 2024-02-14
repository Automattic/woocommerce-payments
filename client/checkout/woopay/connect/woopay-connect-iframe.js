/**
 * External dependencies
 */
import React, { useEffect, useRef } from 'react';

/**
 * Internal dependencies
 */
import { getConfig } from 'wcpay/utils/checkout';
import {
	INJECTED_STATE,
	setConnectIframeInjectedState,
} from 'wcpay/checkout/woopay/connect/connect-utils';

export const WooPayConnectIframe = () => {
	const iframeRef = useRef();

	const getWoopayConnectUrl = () => {
		const tracksUserId = JSON.stringify(
			getConfig( 'tracksUserIdentity' )
		);

		const urlParams = new URLSearchParams();
		urlParams.append( 'testMode', getConfig( 'testMode' ) );
		urlParams.append( 'source_url', window.location.href );
		urlParams.append( 'tracksUserIdentity', tracksUserId );

		return getConfig( 'woopayHost' ) + '/connect/?' + urlParams.toString();
	};

	useEffect( () => {
		if ( ! iframeRef.current ) {
			return;
		}

		const iframe = iframeRef.current;
		iframe.addEventListener( 'load', () => {
			setConnectIframeInjectedState( INJECTED_STATE.INJECTED );

			window.dispatchEvent(
				new MessageEvent( 'message', {
					source: window,
					origin: getConfig( 'woopayHost' ),
					data: {
						action: 'get_iframe_post_message_success',
						value: ( message ) =>
							iframe.contentWindow.postMessage(
								message,
								getConfig( 'woopayHost' )
							),
					},
				} )
			);
		} );
	}, [] );

	return (
		<iframe
			ref={ iframeRef }
			id="woopay-connect-iframe"
			src={ getWoopayConnectUrl() }
			style={ {
				height: 0,
				width: 0,
				border: 'none',
				margin: 0,
				padding: 0,
				overflow: 'hidden',
				display: 'block',
				visibility: 'hidden',
				position: 'fixed',
				pointerEvents: 'none',
				userSelect: 'none',
			} }
			title="WooPay Connect Direct Checkout"
		/>
	);
};
