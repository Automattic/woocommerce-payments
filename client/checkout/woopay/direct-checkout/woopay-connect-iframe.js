/**
 * External dependencies
 */
import React, { useEffect, useRef } from 'react';

/**
 * Internal dependencies
 */
import { getConfig } from 'wcpay/utils/checkout';

export const WooPayConnectIframe = ( { listeners, actionCallback } ) => {
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
			listeners.setIframePostMessage( ( value ) => {
				iframe.contentWindow.postMessage(
					value,
					getConfig( 'woopayHost' )
				);
			} );
		} );

		const onMessage = ( event ) => {
			const isFromWoopayHost = getConfig( 'woopayHost' ).startsWith(
				event.origin
			);

			if ( ! isFromWoopayHost ) {
				return;
			}

			if ( event.data.action in actionCallback ) {
				const callback = actionCallback[ event.data.action ];
				listeners[ callback ]( event.data.value );
			}
		};

		window.addEventListener( 'message', onMessage );

		return () => {
			window.removeEventListener( 'message', onMessage );
		};
	}, [ actionCallback, listeners ] );

	return (
		<iframe
			ref={ iframeRef }
			src={ getWoopayConnectUrl() }
			title="WooPay Connect Direct Checkout"
		/>
	);
};
