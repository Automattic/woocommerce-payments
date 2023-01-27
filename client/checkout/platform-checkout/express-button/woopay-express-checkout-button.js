/**
 * External dependencies
 */
import { sprintf, __ } from '@wordpress/i18n';
import { useEffect } from 'react';

/**
 * Internal dependencies
 */
import WoopayIcon from './woopay-icon';
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
	const {
		type: buttonType,
		text,
		height,
		size,
		theme,
		context,
	} = buttonSettings;
	const { addToCart, isAddToCartDisabled } = useExpressCheckoutProductHandler(
		api,
		isProductPage
	);

	useEffect( () => {
		if ( ! isPreview ) {
			wcpayTracks.recordUserEvent(
				wcpayTracks.events.PLATFORM_CHECKOUT_EXPRESS_BUTTON_OFFERED,
				{
					context,
				}
			);
		}
	}, [ isPreview, context ] );

	const initPlatformCheckout = ( e ) => {
		e.preventDefault();

		if ( isPreview ) {
			return; // eslint-disable-line no-useless-return
		}

		wcpayTracks.recordUserEvent(
			wcpayTracks.events.PLATFORM_CHECKOUT_EXPRESS_BUTTON_CLICKED,
			{
				context: context,
			}
		);

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
			aria-label={ text }
			onClick={ initPlatformCheckout }
			className="woopay-express-button"
			disabled={ isAddToCartDisabled }
			data-type={ buttonType }
			data-size={ size }
			data-theme={ theme }
			style={ { height: `${ height }px` } }
		>
			{ 'default' !== buttonType
				? sprintf( __( `%s with`, 'woocommerce-payments' ), text )
				: '' }
			<WoopayIcon />
		</button>
	);
};
