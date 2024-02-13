/**
 * External dependencies
 */
import React, { useEffect, useRef } from 'react';

/**
 * Internal dependencies
 */
import { getConfig } from 'wcpay/utils/checkout';
import { getTracksIdentity } from 'tracks';

export const WooPayConnectIframe = ( { listeners, actionCallback } ) => {
	const iframeRef = useRef();

	const getWoopayConnectUrl = () => {
		const urlParams = new URLSearchParams();
		urlParams.append( 'testMode', getConfig( 'testMode' ) );
		urlParams.append( 'source_url', window.location.href );

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

		getTracksIdentity().then( ( tracksUserId ) => {
			if ( ! tracksUserId ) return;
			const urlParams = new URLSearchParams( iframe.src );
			urlParams.append( 'tracksUserIdentity', tracksUserId );
			iframe.src = urlParams.toString();
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
			style={ { height: 0 } }
			title="WooPay Connect Direct Checkout"
		/>
	);
};
