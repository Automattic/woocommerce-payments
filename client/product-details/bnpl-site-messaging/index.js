// global Stripe, wcpayStripeSiteMessaging
/**
 * Internal dependencies
 */
import './style.scss';
import WCPayAPI from 'wcpay/checkout/api';
import { getAppearance, getFontRulesFromPage } from 'wcpay/checkout/upe-styles';
import { getUPEConfig } from 'wcpay/utils/checkout';
import apiRequest from 'wcpay/checkout/utils/request';

/**
 * Initializes the appearance of the payment element by retrieving the UPE configuration
 * from the API and saving the appearance if it doesn't exist. If the appearance already exists,
 * it is simply returned.
 *
 * @param {Object} api The API object used to save the UPE configuration.
 * @return {Promise<Object>} The appearance object for the UPE.
 */
const elementsLocations = {
	bnplProductPage: {
		configKey: 'upeBnplProductPageAppearance',
		appearanceKey: 'bnpl_product_page',
	},
	bnplClassicCart: {
		configKey: 'upeBnplClassicCartAppearance',
		appearanceKey: 'bnpl_classic_cart',
	},
};

async function initializeAppearance( api, location ) {
	const { configKey, appearanceKey } = elementsLocations[ location ];

	const appearance = getUPEConfig( configKey );
	if ( appearance ) {
		return Promise.resolve( appearance );
	}

	return await api.saveUPEAppearance(
		getAppearance( appearanceKey ),
		appearanceKey
	);
}

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
		isCartBlock,
		cartTotal,
	} = window.wcpayStripeSiteMessaging;

	let amount;
	let elementLocation = 'bnplProductPage';

	if ( isCart || isCartBlock ) {
		amount = parseInt( cartTotal, 10 ) || 0;
		elementLocation = 'bnplClassicCart';
	} else {
		amount = parseInt( productVariations.base_product.amount, 10 ) || 0;
	}

	let paymentMessageElement;

	if ( ! isCartBlock ) {
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

		const elementsOptions = {
			appearance: await initializeAppearance( api, elementLocation ),
			fonts: getFontRulesFromPage(),
		};

		paymentMessageElement = api
			.getStripe()
			.elements( elementsOptions )
			.create( 'paymentMethodMessaging', options );
		paymentMessageElement.mount( '#payment-method-message' );
	}

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
		const paymentMessageContainer = document.getElementById(
			'payment-method-message'
		);
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
				const cartCollaterals = document.querySelector(
					'.cart-collaterals'
				);
				const wcBnplHeight = getComputedStyle( cartCollaterals )
					.getPropertyValue( '--wc-bnpl-height' )
					.trim();

				if ( wcBnplHeight ) {
					return;
				}

				const pmme = document.getElementById(
					'payment-method-message'
				);
				const pmmeContainer = document.querySelector(
					'.cart_totals .__PrivateStripeElement'
				);
				setTimeout( () => {
					const pmmeComputedStyle = window.getComputedStyle( pmme );
					const pmmeHeight = parseFloat( pmmeComputedStyle.height );
					const pmmeMarginBottom = parseFloat( bottomMargin );
					const pmmeTotalHeight = pmmeHeight + pmmeMarginBottom;

					const pmmeContainerComputedStyle = window.getComputedStyle(
						pmmeContainer
					);
					const pmmeContainerHeight = parseFloat(
						pmmeContainerComputedStyle.height
					);

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

					pmme.style.setProperty( '--wc-bnpl-margin-bottom', '-4px' );
				}, 2000 );
			} else {
				paymentMessageLoading.remove();
			}
		} );
	}

	return paymentMessageElement;
};
