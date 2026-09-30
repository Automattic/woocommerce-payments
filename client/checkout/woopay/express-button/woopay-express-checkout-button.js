/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import React, { useCallback, useEffect, useState, useRef } from 'react';
import clsx from 'clsx';

/**
 * Internal dependencies
 */
import WoopayIcon from './woopay-icon';
import WoopayIconLight from './woopay-icon-light';
import { expressCheckoutIframe } from './express-checkout-iframe';
import useExpressCheckoutProductHandler, {
	getProductFormElement,
} from './use-express-checkout-product-handler';
import { recordUserEvent } from 'tracks';
import { getConfig } from 'wcpay/utils/checkout';
import { showErrorMessage } from 'wcpay/checkout/woopay/express-button/utils';
import interpolateComponents from '@automattic/interpolate-components';
import {
	appendRedirectionParams,
	deleteSkipWooPayCookie,
} from 'wcpay/checkout/woopay/utils';
import { getAddToCartButtonElement } from 'wcpay/utils/wc-product-page-selectors';
import WooPayFirstPartyAuth from 'wcpay/checkout/woopay/express-button/woopay-first-party-auth';
import { resolveWoopayAppearance } from 'wcpay/checkout/woopay/appearance/resolve';
import { wooPayCardBrands } from 'wcpay/utils/woopay-card-brands';
import { isValidPreferredCard, normalizeBrand } from './preferred-card-utils';

const BUTTON_WIDTH_THRESHOLD = 140;
const CARD_DISPLAY_WIDTH_THRESHOLD = 220;

const BRAND_DISPLAY_NAMES = {
	visa: __( 'Visa', 'woocommerce-payments' ),
	mastercard: __( 'Mastercard', 'woocommerce-payments' ),
	amex: __( 'American Express', 'woocommerce-payments' ),
	discover: __( 'Discover', 'woocommerce-payments' ),
	jcb: __( 'JCB', 'woocommerce-payments' ),
	unionpay: __( 'UnionPay', 'woocommerce-payments' ),
	diners: __( 'Diners Club', 'woocommerce-payments' ),
	// TODO: CB icon not yet shipped in woopay-card-brands; this entry is a
	// placeholder so the display name is ready when the icon lands.
	cartes_bancaires: __( 'Cartes Bancaires', 'woocommerce-payments' ),
};

const ButtonTypeTextMap = {
	default: __( 'WooPay', 'woocommerce-payments' ),
	buy: __( 'Buy with WooPay', 'woocommerce-payments' ),
	donate: __( 'Donate with WooPay', 'woocommerce-payments' ),
	book: __( 'Book with WooPay', 'woocommerce-payments' ),
};

export const WoopayExpressCheckoutButton = ( {
	listenForCartChanges,
	isPreview = false,
	buttonSettings,
	api,
	isProductPage = false,
	emailSelector = '#email',
	buttonAttributes,
	preferredCard = null,
} ) => {
	const buttonWidthTypes = {
		narrow: 'narrow',
		wide: 'wide',
	};
	const onClickCallbackRef = useRef( null );
	const buttonRef = useRef( null );
	const isLoadingRef = useRef( false );
	let {
		height: buttonHeight,
		type: buttonType,
		theme,
		context,
		radius: borderRadius,
	} = buttonSettings;
	const [ isLoading, setIsLoading ] = useState( false );
	const [ measuredWidth, setMeasuredWidth ] = useState( null );
	const buttonSizeMap = new Map();
	buttonSizeMap.set( '40', 'small' );
	buttonSizeMap.set( '48', 'medium' );
	buttonSizeMap.set( '55', 'large' );

	// If we are on the checkout block, we receive button attributes which overwrite the extension specific settings
	if ( typeof buttonAttributes !== 'undefined' ) {
		buttonHeight = buttonAttributes.height || buttonHeight;
		borderRadius = buttonAttributes.borderRadius ?? borderRadius;
	}

	const buttonSize =
		buttonSizeMap.get( buttonHeight?.toString() ) || 'medium';

	const buttonText =
		ButtonTypeTextMap[ buttonType || 'default' ] ??
		ButtonTypeTextMap.default;

	const ThemedWooPayIcon = theme === 'dark' ? WoopayIcon : WoopayIconLight;

	const isFirstPartyAuth = getConfig( 'isWoopayFirstPartyAuthEnabled' );
	const woopayUrl = getConfig( 'woopayHost' ) + '/woopay/';

	const { addToCart, getProductData } =
		useExpressCheckoutProductHandler( api );
	const getProductDataRef = useRef( getProductData );
	const addToCartRef = useRef( addToCart );

	useEffect( () => {
		if ( ! buttonRef.current ) {
			return;
		}

		setMeasuredWidth( buttonRef.current.getBoundingClientRect().width );
	}, [] );

	useEffect( () => {
		if ( ! isPreview ) {
			recordUserEvent( 'woopay_button_load', {
				source: context,
			} );
		}
	}, [ isPreview, context ] );

	const canAddProductToCart = useCallback( () => {
		if ( ! isProductPage ) {
			return true;
		}

		const addToCartButton = getAddToCartButtonElement();

		if (
			addToCartButton &&
			( addToCartButton.disabled ||
				addToCartButton.classList.contains( 'disabled' ) )
		) {
			if (
				addToCartButton.classList.contains(
					'wc-variation-is-unavailable'
				)
			) {
				window.alert(
					window?.wc_add_to_cart_variation_params
						?.i18n_unavailable_text ||
						__(
							'Sorry, this product is unavailable. Please choose a different combination.',
							'woocommerce-payments'
						)
				);
			} else {
				window.alert(
					__(
						'Please select your product options before proceeding.',
						'woocommerce-payments'
					)
				);
			}

			return false;
		}

		return true;
	}, [ isProductPage ] );

	const onClickOtpFlow = useCallback(
		( e ) => {
			e?.preventDefault();

			if ( isPreview ) {
				return; // eslint-disable-line no-useless-return
			}

			recordUserEvent( 'woopay_button_click', {
				source: context,
			} );

			deleteSkipWooPayCookie();

			if ( ! canAddProductToCart() ) {
				return;
			}

			if ( isProductPage ) {
				const productData = getProductDataRef.current();
				if ( ! productData ) {
					return;
				}

				addToCartRef.current( productData ).then( ( res ) => {
					if ( res.error ) {
						if ( res.submit ) {
							// Some extensions needs to submit the form
							// to show error messages.
							getProductFormElement()?.submit();
						}
						return;
					}

					expressCheckoutIframe( api, context, emailSelector );
				} );
			} else {
				expressCheckoutIframe( api, context, emailSelector );
			}
		},
		[
			api,
			context,
			emailSelector,
			isPreview,
			isProductPage,
			canAddProductToCart,
		]
	);

	const onClickFirstPartyAuthFlow = useCallback(
		( e ) => {
			e.preventDefault();

			if ( isPreview || isLoadingRef.current ) {
				return;
			}

			recordUserEvent( 'woopay_button_click', {
				source: context,
			} );

			deleteSkipWooPayCookie();

			if ( ! canAddProductToCart() ) {
				return;
			}

			// Set isLoadingRef to true to prevent multiple clicks.
			isLoadingRef.current = true;
			setIsLoading( true );

			const appearance = resolveWoopayAppearance();

			if ( isProductPage ) {
				const productData = getProductDataRef.current();

				if ( ! productData ) {
					return;
				}

				if ( typeof listenForCartChanges?.stop === 'function' ) {
					// Temporarily stop listening for cart changes to prevent
					// rendering a new button + iFrame when the cart is updated.
					listenForCartChanges.stop();
				}

				addToCartRef.current( productData ).then( () => {
					if ( typeof listenForCartChanges?.start === 'function' ) {
						// Start listening for cart changes, again.
						listenForCartChanges.start();
					}
					WooPayFirstPartyAuth.getWooPaySessionFromMerchant( {
						_ajax_nonce: getConfig( 'woopaySessionNonce' ),
						appearance: appearance,
					} )
						.then( async ( response ) => {
							if (
								response?.blog_id &&
								response?.data?.session
							) {
								const sessionResponse =
									await WooPayFirstPartyAuth.sendPreemptiveSessionDataToWooPay(
										response
									);

								if ( sessionResponse?.is_error ) {
									onClickOtpFlow( null );

									onClickCallbackRef.current = onClickOtpFlow;
									isLoadingRef.current = false;
									setIsLoading( false );
									return;
								}

								window.location.href = appendRedirectionParams(
									sessionResponse.redirect_url
								);
							} else {
								onClickCallbackRef.current = onClickOtpFlow;
								throw new Error( response?.data );
							}
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
				WooPayFirstPartyAuth.getWooPaySessionFromMerchant( {
					_ajax_nonce: getConfig( 'woopaySessionNonce' ),
					order_id: getConfig( 'order_id' ),
					key: getConfig( 'key' ),
					billing_email: getConfig( 'billing_email' ),
					appearance: appearance,
				} )
					.then( async ( response ) => {
						if ( response?.blog_id && response?.data?.session ) {
							const sessionResponse =
								await WooPayFirstPartyAuth.sendPreemptiveSessionDataToWooPay(
									response
								);

							if ( sessionResponse?.is_error ) {
								onClickOtpFlow( null );

								onClickCallbackRef.current = onClickOtpFlow;
								isLoadingRef.current = false;
								setIsLoading( false );
								return;
							}

							window.location.href = appendRedirectionParams(
								sessionResponse.redirect_url
							);
						} else {
							onClickCallbackRef.current = onClickOtpFlow;
							throw new Error( response?.data );
						}
					} )
					?.catch( () => {
						const errorMessage = __(
							'Something went wrong. Please try again.',
							'woocommerce-payments'
						);
						showErrorMessage( context, errorMessage );
						isLoadingRef.current = false;
						setIsLoading( false );
					} );
			}
		},
		[
			canAddProductToCart,
			context,
			isPreview,
			isProductPage,
			listenForCartChanges,
			onClickOtpFlow,
		]
	);

	useEffect( () => {
		if ( getConfig( 'isWoopayFirstPartyAuthEnabled' ) ) {
			onClickCallbackRef.current = onClickFirstPartyAuthFlow;
			WooPayFirstPartyAuth.init();
		} else {
			onClickCallbackRef.current = onClickOtpFlow;
		}
	}, [ onClickFirstPartyAuthFlow, onClickOtpFlow ] );

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

	let buttonWidthType = null;
	if ( measuredWidth !== null ) {
		buttonWidthType =
			measuredWidth > BUTTON_WIDTH_THRESHOLD
				? buttonWidthTypes.wide
				: buttonWidthTypes.narrow;
	}

	const normalizedBrand = isValidPreferredCard( preferredCard )
		? normalizeBrand( preferredCard.brand )
		: null;

	const cardBrandIcon =
		normalizedBrand && measuredWidth >= CARD_DISPLAY_WIDTH_THRESHOLD
			? wooPayCardBrands.find(
					( brand ) => brand.name === normalizedBrand
			  )
			: null;

	const renderButtonContent = () => {
		if ( cardBrandIcon && preferredCard ) {
			return (
				<div className="button-content">
					<ThemedWooPayIcon />
					<span
						className="woopay-button-separator"
						aria-hidden="true"
					/>
					<img
						src={ cardBrandIcon.component }
						alt={
							BRAND_DISPLAY_NAMES[ normalizedBrand ] ||
							normalizedBrand
						}
						className="woopay-button-card-brand"
					/>
					<span className="woopay-button-last4">
						{ preferredCard.last4 }
					</span>
				</div>
			);
		}

		return (
			<div className="button-content">
				{ interpolateComponents( {
					mixedString: buttonText.replace(
						ButtonTypeTextMap.default,
						'{{wooPayLogo /}}'
					),
					components: {
						wooPayLogo: <ThemedWooPayIcon />,
					},
				} ) }
			</div>
		);
	};

	const brandDisplayName =
		BRAND_DISPLAY_NAMES[ normalizedBrand ] || normalizedBrand;

	const ariaLabel = cardBrandIcon
		? sprintf(
				/* translators: %1$s: card brand display name (e.g. "American Express"), %2$s: last 4 digits of card */
				__( 'WooPay with %1$s ending in %2$s', 'woocommerce-payments' ),
				brandDisplayName,
				preferredCard.last4
		  )
		: buttonText;

	const sharedProps = {
		ref: buttonRef,
		'aria-label': ariaLabel,
		onClick: ( e ) => onClickCallbackRef.current( e ),
		className: clsx( 'woopay-express-button', {
			'is-loading': isLoading,
		} ),
		'data-type': buttonType,
		'data-size': buttonSize,
		'data-theme': theme,
		'data-width-type': buttonWidthType,
		style: {
			height: `${ buttonHeight }px`,
			borderRadius: `${ borderRadius }px`,
		},
	};

	const buttonContent = isLoading ? (
		<span className="wc-block-components-spinner" />
	) : (
		renderButtonContent()
	);

	return (
		<div id="wcpay-woopay-button">
			{ isFirstPartyAuth ? (
				<a
					key={ `${ buttonType }-${ theme }-${ buttonSize }` }
					{ ...sharedProps }
					href={ isLoading ? undefined : woopayUrl }
					aria-disabled={ isLoading || undefined }
					tabIndex={ isLoading ? -1 : undefined }
				>
					{ buttonContent }
				</a>
			) : (
				<button
					key={ `${ buttonType }-${ theme }-${ buttonSize }` }
					{ ...sharedProps }
					disabled={ isLoading }
					type="button"
				>
					{ buttonContent }
				</button>
			) }
		</div>
	);
};
