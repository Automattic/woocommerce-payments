/**
 * External dependencies
 */
import { sprintf, __ } from '@wordpress/i18n';
import React, { useCallback, useEffect, useState, useRef } from 'react';

/**
 * Internal dependencies
 */
import WoopayIcon from './woopay-icon';
import WoopayIconLight from './woopay-icon-light';
import { expressCheckoutIframe } from './express-checkout-iframe';
import useExpressCheckoutProductHandler from './use-express-checkout-product-handler';
import wcpayTracks from 'tracks';
import { getConfig } from 'wcpay/utils/checkout';
import request from 'wcpay/checkout/utils/request';
import { buildAjaxURL } from 'wcpay/payment-request/utils';

export const WoopayExpressCheckoutButton = ( {
	isPreview = false,
	buttonSettings,
	api,
	isProductPage = false,
	emailSelector = '#email',
} ) => {
	const buttonWidthTypes = {
		narrow: 'narrow',
		wide: 'wide',
	};
	const initWoopayRef = useRef();
	const buttonRef = useRef();
	const { type: buttonType, height, size, theme, context } = buttonSettings;
	const [ buttonWidthType, setButtonWidthType ] = useState(
		buttonWidthTypes.wide
	);

	const text =
		buttonType !== 'default'
			? sprintf(
					__( `%s with`, 'woocommerce-payments' ),
					buttonType.charAt( 0 ).toUpperCase() +
						buttonType.slice( 1 ).toLowerCase()
			  )
			: '';
	const ThemedWooPayIcon = theme === 'dark' ? WoopayIcon : WoopayIconLight;

	const {
		addToCart,
		getProductData,
		isAddToCartDisabled,
	} = useExpressCheckoutProductHandler( api, isProductPage );

	let sessionDataPromise = null;
	if ( ! isProductPage ) {
		sessionDataPromise = request(
			buildAjaxURL( getConfig( 'wcAjaxUrl' ), 'get_woopay_session' ),
			{
				_ajax_nonce: getConfig( 'woopaySessionNonce' ),
			}
		).then( ( response ) => {
			return new Promise( ( resolve ) => {
				resolve( response );
			} );
		} );
	}

	useEffect( () => {
		if ( ! buttonRef.current ) {
			return;
		}

		const buttonWidth = buttonRef.current.getBoundingClientRect().width;
		const isButtonWide = buttonWidth > 140;
		setButtonWidthType(
			isButtonWide ? buttonWidthTypes.wide : buttonWidthTypes.narrow
		);
	}, [ buttonWidthTypes.narrow, buttonWidthTypes.wide ] );

	useEffect( () => {
		if ( ! isPreview ) {
			wcpayTracks.recordUserEvent(
				wcpayTracks.events.WOOPAY_BUTTON_LOAD,
				{
					source: context,
				}
			);
		}
	}, [ isPreview, context ] );

	const newIframe = useCallback( () => {
		const getWoopayOtpUrl = () => {
			const tracksUserId = JSON.stringify(
				getConfig( 'tracksUserIdentity' )
			);

			const urlParams = new URLSearchParams();
			urlParams.append( 'testMode', getConfig( 'testMode' ) );
			urlParams.append( 'source_url', window.location.href );
			urlParams.append( 'tracksUserIdentity', tracksUserId );

			return getConfig( 'woopayHost' ) + '/otp/?' + urlParams.toString();
		};

		const iframe = document.createElement( 'iframe' );
		iframe.src = getWoopayOtpUrl();
		iframe.height = 0;
		iframe.style.visibility = 'hidden';
		iframe.style.position = 'absolute';
		iframe.style.top = '0';

		iframe.addEventListener( 'load', () => {
			initWoopayRef.current = ( e ) => {
				e.preventDefault();

				if ( isPreview ) {
					return;
				}

				sessionDataPromise.then( ( response ) => {
					iframe.contentWindow.postMessage(
						{
							action: 'setPreemptiveSessionData',
							value: response,
						},
						getConfig( 'woopayHost' )
					);
				} );
			};
		} );

		return iframe;
	}, [ isPreview, sessionDataPromise ] );

	useEffect( () => {
		if ( isPreview || isProductPage ) {
			return;
		}

		buttonRef.current.parentElement.style.position = 'relative';
		buttonRef.current.parentElement.appendChild( newIframe() );

		const onMessage = ( event ) => {
			if (
				! getConfig( 'woopayHost' ).startsWith( event.origin ) ||
				event.data.action !== 'confirmSetPreemptiveSessionData'
			) {
				return;
			}

			if ( isPreview ) {
				return;
			}

			window.location.href = event.data.value.redirect_url;
		};

		window.addEventListener( 'message', onMessage );

		return () => {
			window.removeEventListener( 'message', onMessage );
		};
	}, [ isPreview, isProductPage, newIframe ] );

	initWoopayRef.current = ( e ) => {
		e.preventDefault();

		if ( isPreview ) {
			return; // eslint-disable-line no-useless-return
		}

		wcpayTracks.recordUserEvent( wcpayTracks.events.WOOPAY_BUTTON_CLICK, {
			source: context,
		} );

		if ( isProductPage ) {
			const productData = getProductData();

			if ( ! productData ) {
				return;
			}

			addToCart( productData )
				.then( () => {
					expressCheckoutIframe( api, context, emailSelector );
				} )
				.catch( () => {
					// handle error.
				} );
		} else {
			expressCheckoutIframe( api, context, emailSelector );
		}
	};

	return (
		<button
			ref={ buttonRef }
			key={ `${ buttonType }-${ theme }-${ size }` }
			aria-label={ buttonType !== 'default' ? text : __( 'WooPay' ) }
			onClick={ ( e ) => initWoopayRef.current( e ) }
			className="woopay-express-button"
			disabled={ isAddToCartDisabled }
			data-type={ buttonType }
			data-size={ size }
			data-theme={ theme }
			data-width-type={ buttonWidthType }
			style={ { height: `${ height }px` } }
		>
			{ text }
			<ThemedWooPayIcon />
		</button>
	);
};
