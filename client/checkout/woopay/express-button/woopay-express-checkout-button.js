/**
 * External dependencies
 */
import { sprintf, __ } from '@wordpress/i18n';
import { useEffect } from 'react';

/**
 * Internal dependencies
 */
import WoopayIcon from './woopay-icon';
import WoopayIconLight from './woopay-icon-light';
import { expressCheckoutIframe } from './express-checkout-iframe';
import useExpressCheckoutProductHandler from './use-express-checkout-product-handler';
import wcpayTracks from 'tracks';

export const WoopayExpressCheckoutButton = ( {
	isPreview = false,
	buttonSettings,
	api,
	isProductPage = false,
	emailSelector = '#email',
} ) => {
	const { type: buttonType, height, size, theme, context } = buttonSettings;
	const text =
		buttonType !== 'default'
			? sprintf(
					__( `%s with`, 'woocommerce-payments' ),
					buttonType.charAt( 0 ).toUpperCase() +
						buttonType.slice( 1 ).toLowerCase()
			  )
			: '';
	const ThemedWooPayIcon = theme === 'dark' ? WoopayIcon : WoopayIconLight;

	const { addToCart, isAddToCartDisabled } = useExpressCheckoutProductHandler(
		api,
		isProductPage
	);

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

	const initWooPay = ( e ) => {
		e.preventDefault();

		if ( isPreview ) {
			return; // eslint-disable-line no-useless-return
		}

		wcpayTracks.recordUserEvent( wcpayTracks.events.WOOPAY_BUTTON_CLICK, {
			source: context,
		} );

		if ( isProductPage ) {
			addToCart()
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
			key={ `${ buttonType }-${ theme }-${ size }` }
			aria-label={ buttonType !== 'default' ? text : __( 'WooPay' ) }
			onClick={ initWooPay }
			className="woopay-express-button"
			disabled={ isAddToCartDisabled }
			data-type={ buttonType }
			data-size={ size }
			data-theme={ theme }
			style={ { height: `${ height }px` } }
		>
			{ text }
			<ThemedWooPayIcon />
		</button>
	);
};
