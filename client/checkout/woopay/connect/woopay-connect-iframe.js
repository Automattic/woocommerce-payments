/**
 * External dependencies
 */
import React, { useEffect, useRef, useState } from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { getConfig } from 'wcpay/utils/checkout';
import { getTracksIdentity } from 'tracks';
import {
	INJECTED_STATE,
	setConnectIframeInjectedState,
} from 'wcpay/checkout/woopay/connect/connect-utils';

export const WooPayConnectIframe = () => {
	const iframeRef = useRef();
	const [ iframeUrl, setIframeUrl ] = useState( '' );

	useEffect( () => {
		const fetchConfigAndSetIframeUrl = async () => {
			const testMode = getConfig( 'testMode' );
			const woopayHost = getConfig( 'woopayHost' );
			const blogId = getConfig( 'woopayMerchantId' );
			const urlParams = new URLSearchParams( {
				testMode,
				source_url: window.location.href, // TODO: refactor this to camel case.
				blogId,
			} );

			const tracksUserId = await getTracksIdentity();
			if ( tracksUserId ) {
				urlParams.append( 'tracksUserIdentity', tracksUserId );
			}

			setIframeUrl(
				`${ woopayHost }/connect/?${ urlParams.toString() }`
			);
		};

		fetchConfigAndSetIframeUrl();
	}, [] );

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
	}, [ iframeUrl ] );

	return (
		<iframe
			ref={ iframeRef }
			id="woopay-connect-iframe"
			src={ iframeUrl }
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
			title={ __(
				'WooPay Connect Direct Checkout',
				'woocommerce-payments'
			) }
		/>
	);
};
