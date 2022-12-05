/**
 * External dependencies
 */
import { sprintf, __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import WoopayIcon from './woopay-icon';
import { expressCheckoutIframe } from './express-checkout-iframe';

export const WoopayExpressCheckoutButton = ( {
	isPreview = false,
	buttonSettings,
	api,
} ) => {
	const { type: buttonType, text, height, size, theme } = buttonSettings;

	const initPlatformCheckout = ( e ) => {
		e.preventDefault();

		if ( isPreview ) {
			return; // eslint-disable-line no-useless-return
		}

		expressCheckoutIframe( api );
	};

	return (
		<button
			key={ `${ buttonType }-${ theme }-${ size }` }
			onClick={ initPlatformCheckout }
			className="woopay-express-button"
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
