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
async function initializeAppearance( api ) {
	const appearance = getUPEConfig( 'upeBnplProductPageAppearance' );
	if ( appearance ) {
		return Promise.resolve( appearance );
	}

	return await api.saveUPEAppearance(
		getAppearance( 'bnpl_product_page' ),
		'bnpl_product_page'
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
	} = window.wcpayStripeSiteMessaging;

	const api = new WCPayAPI(
		{
			publishableKey: publishableKey,
			accountId: accountId,
			locale: locale,
		},
		apiRequest
	);
	const options = {
		amount: parseInt( productVariations.base_product.amount, 10 ) || 0,
		currency: productVariations.base_product.currency || 'USD',
		paymentMethodTypes: paymentMethods || [],
		countryCode: country, // Customer's country or base country of the store.
	};
	const elementsOptions = {
		appearance: await initializeAppearance( api ),
		fonts: getFontRulesFromPage(),
	};
	const paymentMessageElement = api
		.getStripe()
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

	// Only attempt to adjust the margins if the price element is found.
	if ( priceElement ) {
		const style = window.getComputedStyle( priceElement );
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
		document
			.getElementById( 'payment-method-message' )
			.style.setProperty( '--wc-bnpl-margin-bottom', bottomMargin );

		// When the payment message element is ready, add the `ready` class so the necessary CSS rules are applied.
		paymentMessageElement.on( 'ready', () => {
			document
				.getElementById( 'payment-method-message' )
				.classList.add( 'ready' );
		} );
	}

	return paymentMessageElement;
};
