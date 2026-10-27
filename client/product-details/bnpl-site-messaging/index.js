// global Stripe, wcpayStripeSiteMessaging
/**
 * Internal dependencies
 */
import './style.scss';
import WCPayAPI from 'wcpay/checkout/api';
import { getAppearance, getFontRulesFromPage } from 'wcpay/checkout/upe-styles';
import {
	getCachedAppearance,
	setCachedAppearance,
	dispatchAppearanceEvent,
} from 'wcpay/utils/appearance-cache';
import apiRequest from 'wcpay/checkout/utils/request';

const elementsLocations = {
	bnplProductPage: 'bnpl_product_page',
	bnplClassicCart: 'bnpl_classic_cart',
};

// Tracks the cart PMME's height. Replaced each time the cart totals update re-mounts the element.
let cartPmmeResizeObserver;

export const initializeBnplSiteMessaging = async () => {
	const {
		productVariations,
		country,
		locale,
		accountId,
		publishableKey,
		paymentMethods,
		currencyCode,
		isCart,
		cartTotal,
		shouldShowPMME,
	} = window.wcpayStripeSiteMessaging;

	let amount;
	let elementLocation = 'bnplProductPage';
	const paymentMessageContainer = document.getElementById(
		'payment-method-message'
	);

	if ( isCart ) {
		amount = parseInt( cartTotal, 10 ) || 0;
		elementLocation = 'bnplClassicCart';
	} else {
		amount = parseInt( productVariations.base_product.amount, 10 ) || 0;

		if ( ! shouldShowPMME ) {
			paymentMessageContainer.style.setProperty( 'display', 'none' );
		}
	}

	const api = new WCPayAPI(
		{
			publishableKey: publishableKey,
			accountId: accountId,
			locale: locale,
		},
		apiRequest
	);

	const options = {
		amount: amount,
		currency: currencyCode || 'USD',
		paymentMethodTypes: paymentMethods || [],
		countryCode: country, // Customer's country or base country of the store.
	};

	const location = elementsLocations[ elementLocation ];
	const cacheVersion = window.wcpayStripeSiteMessaging.stylesCacheVersion;
	let appearance = getCachedAppearance( location, cacheVersion );
	if ( ! appearance ) {
		appearance = getAppearance( location );
		dispatchAppearanceEvent( appearance, location );
		setCachedAppearance( location, cacheVersion, appearance );
	}

	const elementsOptions = {
		appearance,
		fonts: getFontRulesFromPage(),
	};

	const stripe = await api.getStripe();

	const paymentMessageElement = stripe
		.elements( elementsOptions )
		.create( 'paymentMethodMessaging', options );
	paymentMessageElement.mount( '#payment-method-message' );

	// This function converts relative units (rem/em) to pixels based on the current font size.
	function convertToPixels( value, baseFontSize ) {
		const units = value.slice( -2 );
		const numericalValue = parseFloat( value );

		switch ( units ) {
			case 'em': // Convert em units to pixels using the base font size. Covers `em` and `rem` units.
				return `${ numericalValue * baseFontSize }px`;
			case 'px': // Value is already in pixels.
				return value;
			default:
				return '0px'; // Default case to avoid potential errors.
		}
	}

	const priceElement =
		document.querySelector( '.price' ) || // For non-block product templates.
		document.querySelector( '.wp-block-woocommerce-product-price' ); // For block product templates.
	const cartTotalElement = document.querySelector(
		'.cart_totals .shop_table'
	);

	// Only attempt to adjust the margins if the price element is found.
	if ( priceElement || cartTotalElement ) {
		const element = priceElement || cartTotalElement;
		const style = window.getComputedStyle( element );
		let bottomMargin = style.marginBottom;

		// Get the computed font size of the price element for 'em' calculations.
		const fontSize = parseFloat( style.fontSize );

		// Get the computed font size of the `<html>` element for 'rem' calculations.
		const rootFontSize = parseFloat(
			window.getComputedStyle( document.documentElement ).fontSize
		);

		// If the margin is specified in 'em' or 'rem', convert it to pixels
		if ( bottomMargin.endsWith( 'em' ) ) {
			bottomMargin = convertToPixels( bottomMargin, fontSize );
		} else if ( bottomMargin.endsWith( 'rem' ) ) {
			bottomMargin = convertToPixels( bottomMargin, rootFontSize );
		}

		// Set the `--wc-bnpl-margin-bottom` CSS variable to the computed bottom margin of the price element.
		paymentMessageContainer.style.setProperty(
			'--wc-bnpl-margin-bottom',
			bottomMargin
		);

		let paymentMessageLoading;
		if ( ! isCart ) {
			paymentMessageLoading = document.createElement( 'div' );
			paymentMessageLoading.classList.add( 'pmme-loading' );
			paymentMessageContainer.prepend( paymentMessageLoading );
		}

		paymentMessageElement.on( 'ready', () => {
			// On the cart page, get the height of the PMME after it's rendered and store it in a CSS variable. This helps
			// prevent layout shifts when the PMME is loaded asynchronously upon cart total update.
			if ( isCart ) {
				paymentMessageContainer.classList.add( 'ready' );
				// An element that won't be removed with the cart total update.
				const cartCollaterals =
					document.querySelector( '.cart-collaterals' );

				const pmme = paymentMessageContainer;
				const pmmeContainer = pmme.querySelector(
					'.__PrivateStripeElement'
				);

				// Stripe doesn't size the iframe until it's on screen, which can be long after `ready` when the cart
				// totals are below the fold. Track its size instead of measuring once, so the reserved height
				// never ends up shorter than the message.
				cartPmmeResizeObserver?.disconnect();
				cartPmmeResizeObserver = new ResizeObserver( () => {
					const pmmeContainerRect =
						pmmeContainer.getBoundingClientRect();

					// Skip while the element is hidden, which happens during cart total updates.
					if ( pmmeContainerRect.height === 0 ) {
						return;
					}

					// Measure up to the bottom of the Stripe container, because the PMME's own height is the
					// fixed one set below.
					const pmmeHeight =
						pmmeContainerRect.bottom -
						pmme.getBoundingClientRect().top;
					const pmmeMarginBottom = parseFloat( bottomMargin );
					const pmmeTotalHeight = pmmeHeight + pmmeMarginBottom;
					const pmmeContainerHeight = pmmeContainerRect.height;

					pmme.style.setProperty( '--wc-bnpl-margin-bottom', '-4px' );

					// Only ever grow the reserved space. A smaller size is Stripe's placeholder or its opening
					// animation after a cart update re-mounts the element, and following it would move the button
					// up and back down while the shopper is looking at it.
					const reservedHeight = parseFloat(
						cartCollaterals.style.getPropertyValue(
							'--wc-bnpl-height'
						)
					);
					if ( pmmeTotalHeight < reservedHeight ) {
						return;
					}

					cartCollaterals.style.setProperty(
						'--wc-bnpl-height',
						pmmeTotalHeight + 'px'
					);
					cartCollaterals.style.setProperty(
						'--wc-bnpl-container-height',
						pmmeContainerHeight - 12 + 'px'
					);

					cartCollaterals.style.setProperty(
						'--wc-bnpl-loader-margin',
						pmmeMarginBottom + 2 + 'px'
					);
				} );
				cartPmmeResizeObserver.observe( pmmeContainer );
			} else {
				paymentMessageLoading?.remove();
			}
		} );
	}

	return paymentMessageElement;
};
