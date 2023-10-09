/**
 * External dependencies
 */
import { sprintf, __ } from '@wordpress/i18n';
import React, { useCallback, useEffect, useState, useRef } from 'react';
import classNames from 'classnames';

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
import { showErrorMessage } from 'wcpay/checkout/woopay/express-button/utils';
import { buildAjaxURL } from 'wcpay/payment-request/utils';

const BUTTON_WIDTH_THRESHOLD = 140;

export const WoopayExpressCheckoutButton = ( {
	listenForCartChanges = {},
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
	const sessionDataPromiseRef = useRef( null );
	const initWoopayRef = useRef( null );
	const buttonRef = useRef( null );
	const isLoadingRef = useRef( false );
	const { type: buttonType, height, size, theme, context } = buttonSettings;
	const [ isLoading, setIsLoading ] = useState( false );
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
	const getProductDataRef = useRef( getProductData );
	const addToCartRef = useRef( addToCart );

	useEffect( () => {
		if ( ! buttonRef.current ) {
			return;
		}

		const buttonWidth = buttonRef.current.getBoundingClientRect().width;
		const isButtonWide = buttonWidth > BUTTON_WIDTH_THRESHOLD;
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

	const defaultOnClick = useCallback(
		( e ) => {
			e.preventDefault();

			if ( isPreview ) {
				return; // eslint-disable-line no-useless-return
			}

			wcpayTracks.recordUserEvent(
				wcpayTracks.events.WOOPAY_BUTTON_CLICK,
				{
					source: context,
				}
			);

			if ( isProductPage ) {
				if ( isAddToCartDisabled ) {
					alert(
						window.wc_add_to_cart_variation_params
							?.i18n_make_a_selection_text ||
							__(
								'Please select all required options to continue.',
								'woocommerce-payments'
							)
					);
					return;
				}

				const productData = getProductDataRef.current();
				if ( ! productData ) {
					return;
				}

				addToCartRef
					.current( productData )
					.then( ( res ) => {
						if ( res.error ) {
							if ( res.submit ) {
								// Some extensions needs to submit the form
								// to show error messages.
								document.querySelector( 'form.cart' ).submit();
							}
							return;
						}

						expressCheckoutIframe( api, context, emailSelector );
					} )
					.catch( () => {
						// handle error.
					} );
			} else {
				expressCheckoutIframe( api, context, emailSelector );
			}
		},
		[
			api,
			context,
			emailSelector,
			isAddToCartDisabled,
			isPreview,
			isProductPage,
		]
	);

	const newIframe = useCallback( () => {
		if ( ! getConfig( 'isWoopayFirstPartyAuthEnabled' ) ) {
			return;
		}

		const getWoopayOtpUrl = () => {
			const tracksUserId = JSON.stringify(
				getConfig( 'tracksUserIdentity' )
			);

			const urlParams = new URLSearchParams();
			urlParams.append( 'testMode', getConfig( 'testMode' ) );
			urlParams.append( 'source_url', window.location.href );
			urlParams.append( 'tracksUserIdentity', tracksUserId );

			return (
				getConfig( 'woopayHost' ) + '/connect/?' + urlParams.toString()
			);
		};

		const iframe = document.createElement( 'iframe' );
		iframe.src = getWoopayOtpUrl();
		iframe.height = 0;
		iframe.style.visibility = 'hidden';
		iframe.style.position = 'absolute';
		iframe.style.top = '0';

		iframe.addEventListener( 'load', () => {
			// Change button's onClick handle to use express checkout flow.
			initWoopayRef.current = ( e ) => {
				e.preventDefault();

				if ( isPreview || isLoadingRef.current ) {
					return;
				}

				// Set isLoadingRef to true to prevent multiple clicks.
				isLoadingRef.current = true;
				setIsLoading( true );

				wcpayTracks.recordUserEvent(
					wcpayTracks.events.WOOPAY_BUTTON_CLICK,
					{
						source: context,
					}
				);

				if ( isProductPage ) {
					const productData = getProductDataRef.current();

					if ( ! productData ) {
						return;
					}

					if ( listenForCartChanges.stop ) {
						// Temporarily stop listening for cart changes to prevent
						// rendering a new button + iFrame when the cart is updated.
						listenForCartChanges.stop();
					}

					addToCartRef.current( productData ).then( () => {
						if ( listenForCartChanges.start ) {
							// Start listening for cart changes, again.
							listenForCartChanges.start();
						}
						request(
							buildAjaxURL(
								getConfig( 'wcAjaxUrl' ),
								'get_woopay_session'
							),
							{
								_ajax_nonce: getConfig( 'woopaySessionNonce' ),
							}
						)
							.then( ( response ) => {
								iframe.contentWindow.postMessage(
									{
										action: 'setPreemptiveSessionData',
										value: response,
									},
									getConfig( 'woopayHost' )
								);
							} )
							.catch( () => {
								const errorMessage = __(
									'Something went wrong. Please try again.',
									'woocommerce-payments'
								);
								showErrorMessage( context, errorMessage );
								isLoadingRef.current = false;
								setIsLoading( false );
							} );
					} );
				} else {
					// Non-product pages already have pre-fetched session data.
					sessionDataPromiseRef.current
						?.then( ( response ) => {
							iframe.contentWindow.postMessage(
								{
									action: 'setPreemptiveSessionData',
									value: response,
								},
								getConfig( 'woopayHost' )
							);
						} )
						.catch( () => {
							const errorMessage = __(
								'Something went wrong. Please try again.',
								'woocommerce-payments'
							);
							showErrorMessage( context, errorMessage );
							isLoadingRef.current = false;
							setIsLoading( false );
						} );
				}
			};
		} );

		return iframe;
	}, [ isProductPage, context, isPreview, listenForCartChanges ] );

	useEffect( () => {
		if ( isPreview || ! getConfig( 'isWoopayFirstPartyAuthEnabled' ) ) {
			return;
		}

		if ( ! isProductPage ) {
			// Start to pre-fetch session data for non-product pages.
			sessionDataPromiseRef.current = request(
				buildAjaxURL( getConfig( 'wcAjaxUrl' ), 'get_woopay_session' ),
				{
					_ajax_nonce: getConfig( 'woopaySessionNonce' ),
				}
			).then( ( response ) => response );
		}

		buttonRef.current.parentElement.style.position = 'relative';
		buttonRef.current.parentElement.appendChild( newIframe() );

		const onMessage = ( event ) => {
			const isFromWoopayHost = getConfig( 'woopayHost' ).startsWith(
				event.origin
			);
			const isSessionDataSuccess =
				event.data.action === 'set_preemptive_session_data_success';
			const isSessionDataError =
				event.data.action === 'set_preemptive_session_data_error';
			const isSessionDataResponse =
				isSessionDataSuccess || isSessionDataError;
			if ( ! isFromWoopayHost || ! isSessionDataResponse ) {
				return;
			}

			if ( isSessionDataSuccess ) {
				window.location.href = event.data.value.redirect_url;
			} else if ( isSessionDataError ) {
				const errorMessage = __(
					'WooPay is unavailable at this time. Please try again.',
					'woocommerce-payments'
				);
				showErrorMessage( context, errorMessage );

				// Set button's default onClick handle to use modal checkout flow.
				initWoopayRef.current = defaultOnClick;
				isLoadingRef.current = false;
				setIsLoading( false );
			}
		};

		window.addEventListener( 'message', onMessage );

		return () => {
			window.removeEventListener( 'message', onMessage );
		};
		// Note: Any changes to this dependency array may cause a duplicate iframe to be appended.
	}, [ context, defaultOnClick, isPreview, isProductPage, newIframe ] );

	useEffect( () => {
		// Set button's default onClick handle to use modal checkout flow.
		initWoopayRef.current = defaultOnClick;
	}, [ defaultOnClick ] );

	useEffect( () => {
		const handlePageShow = ( event ) => {
			// Re-enable the button after navigating back/forward to the page if bfcache is used.
			if ( event?.persisted ) {
				isLoadingRef.current = false;
				setIsLoading( false );
			}
		};

		window.addEventListener( 'pageshow', handlePageShow );

		return () => {
			window.removeEventListener( 'pageshow', handlePageShow );
		};
	}, [] );

	return (
		<button
			ref={ buttonRef }
			key={ `${ buttonType }-${ theme }-${ size }` }
			aria-label={ buttonType !== 'default' ? text : __( 'WooPay' ) }
			onClick={ ( e ) => initWoopayRef.current( e ) }
			className={ classNames( 'woopay-express-button', {
				'is-loading': isLoading,
			} ) }
			data-type={ buttonType }
			data-size={ size }
			data-theme={ theme }
			data-width-type={ buttonWidthType }
			style={ { height: `${ height }px` } }
			disabled={ isLoading }
		>
			{ isLoading ? (
				<span className="wc-block-components-spinner" />
			) : (
				<>
					{ text }
					<ThemedWooPayIcon />
				</>
			) }
		</button>
	);
};
