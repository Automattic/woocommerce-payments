// global Stripe, wcpayStripeSiteMessaging
/**
 * Internal dependencies
 */
import './style.scss';

export const initializeBnplSiteMessaging = () => {
	const {
		productVariations,
		country,
		publishableKey,
		paymentMethods,
	} = window.wcpayStripeSiteMessaging;

	// eslint-disable-next-line no-undef
	const stripe = Stripe( publishableKey );
	const options = {
		amount: parseInt( productVariations.base_product.amount, 10 ) || 0,
		currency: productVariations.base_product.currency || 'USD',
		paymentMethodTypes: paymentMethods || [],
		countryCode: country, // Customer's country or base country of the store.
	};
	const paymentMessageElement = stripe
		.elements()
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
